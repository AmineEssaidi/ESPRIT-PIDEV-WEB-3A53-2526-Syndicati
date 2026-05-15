/**
 * Face ID Manager
 * Handles local face recognition using face-api.js.
 */
const FaceID = (function () {

    const CONFIG = {
        modelPath: (function () {
            // Support for subfolder setups (e.g., /Syndicati/public/...)
            const pathParts = window.location.pathname.split('/');
            const publicIdx = pathParts.indexOf('public');
            if (publicIdx !== -1) {
                return pathParts.slice(0, publicIdx + 1).join('/') + '/models/face-api';
            }
            // Support for standard root setup
            return '/models/face-api';
        })(),
        endpoints: {
            enroll: '/face/enroll',
            auth: '/face/auth'
        },
        minGoodFrames: 20,
        distanceThreshold: 0.5,
        blinkThreshold: 0.25 // Eye aspect ratio
    };

    let modelsLoaded = false;

    // --- Utils ---

    function getDeviceId() {
        let id = localStorage.getItem('face_device_id');
        if (!id) {
            id = 'device_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            localStorage.setItem('face_device_id', id);
        }
        return id;
    }

    async function loadModels() {
        if (modelsLoaded) return;

        console.log('FaceID: Loading models from', CONFIG.modelPath);

        // Retry mechanism for faceapi availability (handles async fallback/CDN)
        let retryCount = 0;
        const maxRetries = 40; // 20 seconds
        while (typeof faceapi === 'undefined' && retryCount < maxRetries) {
            console.warn(`FaceID: faceapi undefined, retry ${retryCount + 1}/${maxRetries}...`);
            await new Promise(resolve => setTimeout(resolve, 500));
            retryCount++;
        }

        // Ensure faceapi is available (use window.faceapi in case of strict/UMD)
        const faceapiGlobal = typeof faceapi !== 'undefined' ? faceapi : (typeof window !== 'undefined' && window.faceapi);
        if (!faceapiGlobal) {
            const errorMsg = 'face-api.js not loaded. (Global faceapi variable is undefined after retries). Please check if face-api.min.js is correctly included in the template and reachable.';
            console.error(errorMsg);
            throw new Error(errorMsg);
        }
        // Use the resolved global for the rest of this load
        if (typeof globalThis !== 'undefined') globalThis.faceapi = faceapiGlobal;
        if (typeof window !== 'undefined') window.faceapi = faceapiGlobal;

        try {
            await Promise.all([
                faceapiGlobal.nets.ssdMobilenetv1.loadFromUri(CONFIG.modelPath),
                faceapiGlobal.nets.faceLandmark68Net.loadFromUri(CONFIG.modelPath),
                faceapiGlobal.nets.faceRecognitionNet.loadFromUri(CONFIG.modelPath)
            ]);
            modelsLoaded = true;
            console.log('FaceID Models Loaded Successfully');
        } catch (e) {
            console.error('FaceID: Failed to load models from ' + CONFIG.modelPath, e);
            throw new Error('Failed to load Face ID neural network models. Please ensure the models folder exists and is reachable.');
        }
    }

    /**
     * Calculate Eye Aspect Ratio (EAR) for blink detection
     */
    function calculateEAR(eye) {
        // Compute the distances between the vertical eye landmarks
        const v1 = Math.sqrt(Math.pow(eye[1].x - eye[5].x, 2) + Math.pow(eye[1].y - eye[5].y, 2));
        const v2 = Math.sqrt(Math.pow(eye[2].x - eye[4].x, 2) + Math.pow(eye[2].y - eye[4].y, 2));
        // Compute the distance between the horizontal eye landmark
        const h = Math.sqrt(Math.pow(eye[0].x - eye[3].x, 2) + Math.pow(eye[0].y - eye[3].y, 2));

        return (v1 + v2) / (2.0 * h);
    }

    // --- Core Logic ---

    /**
     * Start enrollment process
     */
    async function startEnrollment(videoElement, pin, onProgress) {
        await loadModels();
        const deviceId = getDeviceId();
        const embeddings = [];
        let hasBlinked = false;
        let headMoved = false;
        const lastPositions = [];

        return new Promise((resolve, reject) => {
            const captureInterval = setInterval(async () => {
                const detection = await faceapi.detectSingleFace(videoElement)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    const landmarks = detection.landmarks;
                    const leftEye = landmarks.getLeftEye();
                    const rightEye = landmarks.getRightEye();
                    const ear = (calculateEAR(leftEye) + calculateEAR(rightEye)) / 2;

                    if (ear < CONFIG.blinkThreshold) hasBlinked = true;

                    // Movement check
                    const currentPos = detection.detection.box;
                    if (lastPositions.length > 0) {
                        const dist = Math.sqrt(Math.pow(currentPos.x - lastPositions[0].x, 2) + Math.pow(currentPos.y - lastPositions[0].y, 2));
                        if (dist > 5) headMoved = true;
                    }
                    lastPositions.unshift(currentPos);
                    if (lastPositions.length > 5) lastPositions.pop();

                    embeddings.push(Array.from(detection.descriptor));

                    if (onProgress) onProgress(embeddings.length / CONFIG.minGoodFrames);

                    if (embeddings.length >= CONFIG.minGoodFrames) {
                        clearInterval(captureInterval);

                        if (!hasBlinked || !headMoved) {
                            reject(new Error('Liveness check failed. Please blink and move your head slightly.'));
                            return;
                        }

                        // Average embeddings
                        const avgEmbedding = embeddings[0].map((_, i) =>
                            embeddings.reduce((sum, embed) => sum + embed[i], 0) / embeddings.length
                        );

                        // Send to server
                        try {
                            const response = await fetch(CONFIG.endpoints.enroll, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    embedding: avgEmbedding,
                                    pin: pin,
                                    deviceId: deviceId
                                })
                            });

                            const result = await response.json();
                            if (response.ok) resolve(result);
                            else reject(new Error(result.error || 'Enrollment failed'));
                        } catch (e) {
                            reject(e);
                        }
                    }
                }
            }, 200);
        });
    }

    /**
     * Authenticate user with Face ID
     */
    async function authenticate(videoElement, email, pin) {
        await loadModels();
        const deviceId = getDeviceId();

        // Capture one good frame for auth (or average 3-5 frames for better accuracy)
        const detection = await faceapi.detectSingleFace(videoElement)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            throw new Error('No face detected. Please look at the camera.');
        }

        const response = await fetch(CONFIG.endpoints.auth, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: email,
                embedding: Array.from(detection.descriptor),
                pin: pin,
                deviceId: deviceId
            })
        });

        const result = await response.json();
        if (response.ok) return result;
        throw new Error(result.error || 'Authentication failed');
    }

    return {
        startEnrollment,
        authenticate,
        getDeviceId
    };

})();
