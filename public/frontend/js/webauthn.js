/**
 * WebAuthn Manager
 * Handles registration and management of biometric credentials.
 */
const WebAuthn = (function () {

    // Config
    const ROUTES = {
        registerOptions: '/webauthn/register/options',
        registerVerify: '/webauthn/register/verify',
        loginOptions: '/webauthn/login/options',
        loginVerify: '/webauthn/login/verify',
        listCredentials: '/webauthn/credentials',
        removeCredential: '/webauthn/credentials/' // + id
    };

    // --- Utils ---

    function bufferToBase64url(buffer) {
        const bytes = new Uint8Array(buffer);
        let str = '';
        for (const charCode of bytes) {
            str += String.fromCharCode(charCode);
        }
        const base64 = btoa(str);
        return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    function base64urlToBuffer(base64url) {
        const padding = '='.repeat((4 - base64url.length % 4) % 4);
        const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/') + padding;
        const str = atob(base64);
        const buffer = new Uint8Array(str.length);
        for (let i = 0; i < str.length; i++) {
            buffer[i] = str.charCodeAt(i);
        }
        return buffer.buffer;
    }

    /**
     * Recursive function to convert Base64URL strings in the options object to ArrayBuffers
     */
    function prepareOptions(options) {
        // user.id
        if (options.user && options.user.id && typeof options.user.id === 'string') {
            options.user.id = base64urlToBuffer(options.user.id);
        }

        // challenge
        if (options.challenge && typeof options.challenge === 'string') {
            options.challenge = base64urlToBuffer(options.challenge);
        }

        // allowCredentials
        if (options.allowCredentials && Array.isArray(options.allowCredentials)) {
            options.allowCredentials = options.allowCredentials.map((cred) => {
                const id = cred.id || cred.id;
                if (id && typeof id === 'string') {
                    cred.id = base64urlToBuffer(id);
                }
                return cred;
            });
        }

        return options;
    }

    // --- Core Functions ---

    async function register() {
        try {
            const response = await fetch(ROUTES.registerOptions, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Failed to fetch registration options');
            const optionsJson = await response.json();
            if (optionsJson.error) throw new Error(optionsJson.error);

            const publicKey = prepareOptions(optionsJson);
            const credential = await navigator.credentials.create({ publicKey });

            const credentialData = {
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    attestationObject: bufferToBase64url(credential.response.attestationObject),
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON)
                }
            };

            const verifyResponse = await fetch(ROUTES.registerVerify, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(credentialData)
            });

            const verifyJson = await verifyResponse.json();
            if (verifyResponse.ok && verifyJson.status === 'ok') {
                return { success: true, message: verifyJson.message };
            } else {
                throw new Error(verifyJson.error || 'Verification failed');
            }
        } catch (error) {
            console.error('WebAuthn Registration Error:', error);
            return { success: false, message: error.message };
        }
    }

    async function authenticate(email) {
        try {
            // 1. Get login options
            const response = await fetch(ROUTES.loginOptions, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email })
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.error || 'Failed to fetch login options');
            }

            const optionsJson = await response.json();
            const publicKey = prepareOptions(optionsJson);

            // 2. Get assertion
            const assertion = await navigator.credentials.get({ publicKey });

            // 3. Encode response
            const assertionData = {
                id: assertion.id,
                rawId: bufferToBase64url(assertion.rawId),
                type: assertion.type,
                response: {
                    authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
                    clientDataJSON: bufferToBase64url(assertion.response.clientDataJSON),
                    signature: bufferToBase64url(assertion.response.signature),
                    userHandle: assertion.response.userHandle ? bufferToBase64url(assertion.response.userHandle) : null
                }
            };

            // 4. Verify
            const verifyResponse = await fetch(ROUTES.loginVerify, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(assertionData)
            });

            const verifyJson = await verifyResponse.json();
            if (verifyResponse.ok && verifyJson.status === 'ok') {
                return verifyJson;
            } else {
                throw new Error(verifyJson.error || 'Authentication failed');
            }
        } catch (error) {
            console.error('WebAuthn Authentication Error:', error);
            throw error;
        }
    }

    async function listCredentials() {
        try {
            const response = await fetch(ROUTES.listCredentials);
            if (!response.ok) return [];
            return await response.json();
        } catch (e) {
            console.error(e);
            return [];
        }
    }

    async function removeCredential(id) {
        try {
            const response = await fetch(ROUTES.removeCredential + id, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            return response.ok;
        } catch (e) {
            return false;
        }
    }

    // --- UI Helpers ---

    function renderCredentialList(containerId, credentials) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (credentials.length === 0) {
            container.innerHTML = '<p class="text-muted text-center"><small>No biometric devices registered.</small></p>';
            return;
        }

        let html = '<div class="webauthn-list">';
        credentials.forEach(cred => {
            const date = cred.created_at ? new Date(cred.created_at).toLocaleDateString() : 'Unknown';
            html += `
                <div class="webauthn-item d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background: rgba(255,255,255,0.05);">
                    <div class="d-flex align-items-center gap-2">
                         <i class="bx bx-fingerprint fs-4"></i>
                         <div>
                            <div style="font-size: 0.9rem; font-weight: 600; color: #fff;">Passkey / Biometric</div>
                            <div style="font-size: 0.75rem; color: rgba(255,255,255,0.7);">Registered: ${date}</div>
                         </div>
                    </div>
                    <button class="btn btn-sm btn-icon btn-danger webauthn-delete-btn" data-id="${cred.id_encoded}" title="Remove">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;

        container.querySelectorAll('.webauthn-delete-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                window.confirmObsidianDelete(async () => {
                    const id = btn.dataset.id;
                    const success = await removeCredential(id);
                    if (success) {
                        const list = await listCredentials();
                        renderCredentialList(containerId, list);
                        if (typeof window.showObsidianNotification === 'function') window.showObsidianNotification('Success', 'Credential removed', 'success');
                    } else {
                        if (typeof window.showObsidianNotification === 'function') window.showObsidianNotification('Error', 'Failed to remove', 'error');
                    }
                }, 'Remove Passkey?', 'Are you sure you want to disable this biometric method?');
            });
        });
    }

    return {
        register,
        authenticate,
        listCredentials,
        removeCredential,
        renderCredentialList
    };

})();

// Alias for compatibility
const WebAuthnManager = WebAuthn;
