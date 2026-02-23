/**
 * Overhauled WebRTC Manager for Horizon Spotlight
 * Handles intelligent stream swapping and host-priority layouts.
 */

const webrtcConfig = { 'iceServers': [{ 'urls': 'stun:stun.l.google.com:19302' }] };
let webrtcPeers = {};
let webrtcSignals = {};
let webrtcProcessedIce = {};

async function sendWebrtcSignal(roomToken) {
    if (Object.keys(webrtcSignals).length === 0) return;
    try {
        await fetch(`/video-conference/signal/${roomToken}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ signal: JSON.stringify(webrtcSignals), peerId: window.myConfPeerId })
        });
    } catch (e) { console.error('Signal Send Failed', e); }
}

function createPeerConnection(targetId, localStream, containerId, roomToken, localIsHost = false, targetIsHost = false) {
    if (webrtcPeers[targetId]) return webrtcPeers[targetId];

    const pc = new RTCPeerConnection(webrtcConfig);
    webrtcPeers[targetId] = pc;
    webrtcProcessedIce[targetId] = new Set();

    if (localStream) {
        localStream.getTracks().forEach(track => pc.addTrack(track, localStream));
    } else {
        // Essential: Allow receiving video even if local camera is denied or disabled
        pc.addTransceiver('video', { direction: 'recvonly' });
        pc.addTransceiver('audio', { direction: 'recvonly' });
    }

    pc.onicecandidate = e => {
        if (e.candidate) {
            if (!webrtcSignals[targetId]) webrtcSignals[targetId] = { ice: [] };
            if (!webrtcSignals[targetId].ice) webrtcSignals[targetId].ice = [];
            webrtcSignals[targetId].ice.push(e.candidate);
            sendWebrtcSignal(roomToken);
        }
    };

    pc.ontrack = e => {
        let wrapper = document.getElementById('wrapper-' + targetId);
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.id = 'wrapper-' + targetId;
            wrapper.className = 'confer-video-wrapper transition-all';

            const vid = document.createElement('video');
            vid.autoplay = true;
            vid.playsinline = true;
            vid.className = 'confer-video-el';
            vid.srcObject = e.streams[0];

            vid.onloadedmetadata = () => {
                vid.play().catch(err => console.error('Video Autoplay Error:', err));
            };

            const label = document.createElement('div');
            label.className = 'confer-video-label';
            label.innerHTML = `<span class="label-role">${targetIsHost ? 'Host' : 'Participant'}</span>`;
            if (targetIsHost) {
                label.style.background = 'rgba(255, 193, 7, 0.3)';
                label.style.borderColor = 'rgba(255, 193, 7, 0.5)';
                label.querySelector('span').style.color = '#ffc107';
            }

            wrapper.appendChild(vid);
            wrapper.appendChild(label);

            const container = document.getElementById(containerId);
            if (container) {
                container.appendChild(wrapper);
                setTimeout(() => applySpotlightLogic(containerId, localIsHost), 100);
            }
        }
    };

    pc.oniceconnectionstatechange = () => {
        if (['disconnected', 'failed', 'closed'].includes(pc.iceConnectionState)) {
            const wrapper = document.getElementById('wrapper-' + targetId);
            if (wrapper) wrapper.remove();
            applySpotlightLogic(containerId, localIsHost);
        }
    };

    return pc;
}

/**
 * Intelligent Layout Engine
 * Decides which stream is "Spotlighted" (Big) and which is "PiP" (Small)
 */
function applySpotlightLogic(containerId, localIsHost) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const localWrap = document.getElementById('local-video-wrapper');
    const remoteWrappers = Array.from(container.querySelectorAll('.confer-video-wrapper:not(#local-video-wrapper)'));

    // Find if there's a Host in the room (besides me)
    const hostRemote = remoteWrappers.find(w => w.querySelector('.label-role').textContent.includes('Host'));

    if (localIsHost) {
        // I am Host: Always Spotlight Me. Others are PiP.
        if (localWrap) {
            localWrap.classList.remove('pip-mode');
            localWrap.classList.add('spotlight-active');
        }
        remoteWrappers.forEach(w => {
            w.classList.add('pip-mode');
            w.classList.remove('spotlight-active');
        });
    } else {
        // I am Participant
        if (hostRemote) {
            // Host exists: Spotlight them. I'm PiP.
            hostRemote.classList.remove('pip-mode');
            hostRemote.classList.add('spotlight-active');

            if (localWrap) {
                localWrap.classList.add('pip-mode');
                localWrap.classList.remove('spotlight-active');
            }
            // Other participants are hidden or also PiP (stacked)
            remoteWrappers.forEach(w => {
                if (w !== hostRemote) w.classList.add('pip-mode');
            });
        } else {
            // No Host found: Spotlight the first remote participant if available, else local
            if (remoteWrappers.length > 0) {
                const firstRemote = remoteWrappers[0];
                firstRemote.classList.remove('pip-mode');
                firstRemote.classList.add('spotlight-active');

                if (localWrap) {
                    localWrap.classList.add('pip-mode');
                    localWrap.classList.remove('spotlight-active');
                }

                remoteWrappers.slice(1).forEach(w => {
                    w.classList.add('pip-mode');
                });
            } else if (localWrap) {
                localWrap.classList.remove('pip-mode');
                localWrap.classList.add('spotlight-active');
            }
        }
    }
}

async function processWebrtcSignals(participants, localStream, containerId, roomToken, localIsHost = false) {
    if (!window.myConfSessionId) return;

    for (const p of participants) {
        try {
            if (p.sessionId === window.myConfSessionId) continue;
            const targetId = p.sessionId;
            let incomingSignals = null;
            try { if (p.signal) incomingSignals = JSON.parse(p.signal); } catch (e) { }

            if (!incomingSignals || !incomingSignals[window.myConfSessionId]) {
                if (window.myConfSessionId > targetId) {
                    if (!webrtcPeers[targetId]) {
                        const pc = createPeerConnection(targetId, localStream, containerId, roomToken, localIsHost, p.isHost);
                        const offer = await pc.createOffer();
                        await pc.setLocalDescription(offer);
                        if (!webrtcSignals[targetId]) webrtcSignals[targetId] = { ice: [] };
                        webrtcSignals[targetId].offer = offer;
                        sendWebrtcSignal(roomToken);
                    }
                }
                continue;
            }

            const incoming = incomingSignals[window.myConfSessionId];
            const pc = createPeerConnection(targetId, localStream, containerId, roomToken, localIsHost, p.isHost);

            if (incoming.offer && pc.signalingState === "stable") {
                await pc.setRemoteDescription(new RTCSessionDescription(incoming.offer));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                if (!webrtcSignals[targetId]) webrtcSignals[targetId] = { ice: [] };
                webrtcSignals[targetId].answer = answer;
                sendWebrtcSignal(roomToken);
            }

            if (incoming.answer && pc.signalingState === "have-local-offer") {
                await pc.setRemoteDescription(new RTCSessionDescription(incoming.answer));
            }

            if (incoming.ice) {
                for (const c of incoming.ice) {
                    const cStr = JSON.stringify(c);
                    if (!webrtcProcessedIce[targetId].has(cStr)) {
                        if (pc.remoteDescription) {
                            webrtcProcessedIce[targetId].add(cStr);
                            await pc.addIceCandidate(new RTCIceCandidate(c)).catch(e => console.error('ICE Add Error:', e));
                        } else {
                            // Leave it out of the processed set so it will be retried next cycle
                            console.log('Queuing ICE candidate until remote description is set');
                        }
                    }
                }
            }
        } catch (err) { console.error("Signal Process Error", p.sessionId, err); }
    }
}

function cleanupWebrtc() {
    for (const [id, pc] of Object.entries(webrtcPeers)) pc.close();
    webrtcPeers = {}; webrtcSignals = {}; webrtcProcessedIce = {};
}
