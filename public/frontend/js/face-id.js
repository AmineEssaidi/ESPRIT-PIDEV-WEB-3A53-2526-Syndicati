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
            auth: '/face/auth',
            status: '/face/status',
            remove: '/face/remove'
        },
        minGoodFrames: 12,
        authGoodFrames: 5,
        maxEnrollmentMs: 12000,
        maxAuthMs: 4500,
        captureDelayMs: 90,
        minFaceScore: 0.42,
        minFaceSizeRatio: 0.18,
        distanceThreshold: 0.5,
        blinkThreshold: 0.23 // Eye aspect ratio
    };

    let modelsLoaded = false;
    let modelLoadPromise = null;
    let detectionOptions = null;

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
        if (modelLoadPromise) return modelLoadPromise;

        modelLoadPromise = (async () => {
            console.log('FaceID: Loading models from', CONFIG.modelPath);

            // Retry mechanism for faceapi availability (handles async fallback/CDN)
            let retryCount = 0;
            const maxRetries = 20; // 10 seconds
            while (typeof faceapi === 'undefined' && retryCount < maxRetries) {
                console.warn(`FaceID: faceapi undefined, retry ${retryCount + 1}/${maxRetries}...`);
                await sleep(500);
                retryCount++;
            }

            // Ensure faceapi is available (use window.faceapi in case of strict/UMD)
            const faceapiGlobal = typeof faceapi !== 'undefined' ? faceapi : (typeof window !== 'undefined' && window.faceapi);
            if (!faceapiGlobal) {
                const errorMsg = 'face-api.js not loaded. Please check if face-api.min.js is correctly included and reachable.';
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
                detectionOptions = new faceapiGlobal.SsdMobilenetv1Options({ minConfidence: CONFIG.minFaceScore });
                modelsLoaded = true;
                console.log('FaceID Models Loaded Successfully');
            } catch (e) {
                modelLoadPromise = null;
                console.error('FaceID: Failed to load models from ' + CONFIG.modelPath, e);
                throw new Error('Failed to load Face ID neural network models. Please ensure the models folder exists and is reachable.');
            }
        })();

        return modelLoadPromise;
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function isVideoReady(videoElement) {
        return videoElement && videoElement.readyState >= 2 && videoElement.videoWidth > 0 && videoElement.videoHeight > 0;
    }

    async function waitForVideo(videoElement, timeoutMs = 3000) {
        const started = Date.now();
        while (!isVideoReady(videoElement)) {
            if (Date.now() - started > timeoutMs) {
                throw new Error('Camera stream is not ready yet. Please try again.');
            }
            await sleep(80);
        }
    }

    function isGoodDetection(detection, videoElement) {
        if (!detection || !videoElement) return false;

        const box = detection.detection.box;
        const score = detection.detection.score ?? 1;
        const minDimension = Math.min(videoElement.videoWidth || videoElement.clientWidth || 1, videoElement.videoHeight || videoElement.clientHeight || 1);
        const faceSizeRatio = Math.max(box.width, box.height) / Math.max(minDimension, 1);

        return score >= CONFIG.minFaceScore && faceSizeRatio >= CONFIG.minFaceSizeRatio;
    }

    async function detectFace(videoElement) {
        const detection = await faceapi.detectSingleFace(videoElement, detectionOptions)
            .withFaceLandmarks()
            .withFaceDescriptor();

        return isGoodDetection(detection, videoElement) ? detection : null;
    }

    function averageEmbeddings(embeddings) {
        return embeddings[0].map((_, i) =>
            embeddings.reduce((sum, embed) => sum + embed[i], 0) / embeddings.length
        );
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
        await waitForVideo(videoElement);
        const deviceId = getDeviceId();
        const embeddings = [];
        let hasBlinked = false;
        let headMoved = false;
        const lastPositions = [];

        return new Promise((resolve, reject) => {
            const startedAt = Date.now();
            let settled = false;

            const finish = (fn, value) => {
                if (settled) return;
                settled = true;
                fn(value);
            };

            const capture = async () => {
                if (settled) return;

                try {
                    const detection = await detectFace(videoElement);

                    if (detection) {
                    const landmarks = detection.landmarks;
                    const leftEye = landmarks.getLeftEye();
                    const rightEye = landmarks.getRightEye();
                    const ear = (calculateEAR(leftEye) + calculateEAR(rightEye)) / 2;

                    if (ear < CONFIG.blinkThreshold) hasBlinked = true;

                    // Movement check
                    const currentPos = detection.detection.box;
                    if (lastPositions.length > 0) {
                        const first = lastPositions[lastPositions.length - 1];
                        const dist = Math.sqrt(Math.pow(currentPos.x - first.x, 2) + Math.pow(currentPos.y - first.y, 2));
                        const sizeDelta = Math.abs(currentPos.width - first.width) + Math.abs(currentPos.height - first.height);
                        if (dist > 4 || sizeDelta > 7) headMoved = true;
                    }
                    lastPositions.unshift(currentPos);
                    if (lastPositions.length > 5) lastPositions.pop();

                    embeddings.push(Array.from(detection.descriptor));

                    if (onProgress) onProgress(embeddings.length / CONFIG.minGoodFrames);

                    if (embeddings.length >= CONFIG.minGoodFrames) {
                        // Browser liveness is intentionally softer than Java InsightFace:
                        // one natural cue plus enough clean frames is more reliable than
                        // forcing users to perform both gestures on every webcam.
                        if (!hasBlinked && !headMoved) {
                            finish(reject, new Error('Liveness check needs one cue. Please blink or move your head slightly.'));
                            return;
                        }

                        // Average embeddings
                        const avgEmbedding = averageEmbeddings(embeddings);

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
                            if (response.ok) finish(resolve, result);
                            else finish(reject, new Error(result.error || 'Enrollment failed'));
                        } catch (e) {
                            finish(reject, e);
                        }
                        return;
                    }
                }

                    if (Date.now() - startedAt > CONFIG.maxEnrollmentMs) {
                        finish(reject, new Error('Face scan timed out. Use better light, center your face, then try again.'));
                        return;
                    }

                    setTimeout(capture, CONFIG.captureDelayMs);
                } catch (e) {
                    finish(reject, e);
                }
            };

            capture();
        });
    }

    /**
     * Authenticate user with Face ID
     */
    async function authenticate(videoElement, email, pin) {
        await loadModels();
        await waitForVideo(videoElement);
        const deviceId = getDeviceId();
        const embeddings = [];
        const startedAt = Date.now();

        while (embeddings.length < CONFIG.authGoodFrames && (Date.now() - startedAt) < CONFIG.maxAuthMs) {
            const detection = await detectFace(videoElement);
            if (detection) {
                embeddings.push(Array.from(detection.descriptor));
            }
            if (embeddings.length < CONFIG.authGoodFrames) {
                await sleep(CONFIG.captureDelayMs);
            }
        }

        if (embeddings.length === 0) {
            throw new Error('No face detected. Please look at the camera.');
        }

        const avgEmbedding = averageEmbeddings(embeddings);

        const response = await fetch(CONFIG.endpoints.auth, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: email,
                embedding: avgEmbedding,
                pin: pin,
                deviceId: deviceId
            })
        });

        const result = await response.json();
        if (response.ok) return result;
        throw new Error(result.error || 'Authentication failed');
    }

    async function status() {
        const deviceId = getDeviceId();
        const response = await fetch(CONFIG.endpoints.status, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ deviceId })
        });

        const result = await response.json();
        if (response.ok) return result;
        throw new Error(result.error || 'Could not read Face ID status');
    }

    async function remove() {
        const deviceId = getDeviceId();
        const response = await fetch(CONFIG.endpoints.remove, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ deviceId })
        });

        const result = await response.json();
        if (response.ok) return result;
        throw new Error(result.error || 'Could not remove Face ID');
    }

    return {
        preload: loadModels,
        startEnrollment,
        authenticate,
        status,
        remove,
        getDeviceId
    };

})();
