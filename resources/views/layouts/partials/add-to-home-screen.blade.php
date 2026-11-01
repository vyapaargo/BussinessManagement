{{-- Add to Home Screen / Create Shortcut Modal & Logic --}}
<div id="a2hs-modal" style="display: none; position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; padding: 16px; background-color: rgba(2, 6, 23, 0.75); backdrop-filter: blur(4px); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;" role="dialog" aria-modal="true" aria-labelledby="a2hs-modal-title">
    <div style="position: relative; width: 100%; max-width: 420px; background: #ffffff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border: 1px solid #e2e8f0; overflow: hidden; animation: a2hsFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Header Banner -->
        <div style="background: linear-gradient(135deg, #020617 0%, #1e293b 100%); padding: 22px 20px 20px; color: #ffffff; text-align: center; position: relative;">
            <button type="button" onclick="closeA2HSModal()" style="position: absolute; top: 14px; right: 14px; background: rgba(255, 255, 255, 0.12); border: none; color: #ffffff; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; line-height: 1; transition: background 0.2s;" aria-label="Close dialog">&times;</button>
            
            <div style="width: 58px; height: 58px; margin: 0 auto 12px; background: #ffffff; border-radius: 14px; padding: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25); display: flex; align-items: center; justify-content: center;">
                <img src="{{ asset('images/logo-192.png') }}" alt="Vyapaargo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 10px;">
            </div>
            
            <h3 id="a2hs-modal-title" style="margin: 0 0 4px; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; color: #ffffff;">Add to Home Screen</h3>
            <p style="margin: 0; font-size: 12.5px; color: #cbd5e1; line-height: 1.4;">Create a 1-tap shortcut on your phone to open Vyapaargo instantly.</p>
        </div>

        <!-- Instructions Content -->
        <div style="padding: 20px 20px 16px;">
            <!-- Android / Chrome Instructions (Default) -->
            <div id="a2hs-android-steps" style="display: block;">
                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; shrink-0; margin-top: 1px;">1</div>
                    <div style="flex: 1; font-size: 13.5px; color: #334155; line-height: 1.5;">
                        Tap the browser menu <strong style="color: #0f172a;">(⋮ three dots)</strong> in the top-right corner of Chrome.
                    </div>
                </div>

                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; shrink-0; margin-top: 1px;">2</div>
                    <div style="flex: 1; font-size: 13.5px; color: #334155; line-height: 1.5;">
                        Select <strong style="color: #0f172a;">"Add to Home screen"</strong> or <strong style="color: #0f172a;">"Install app"</strong>.
                    </div>
                </div>

                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; shrink-0; margin-top: 1px;">3</div>
                    <div style="flex: 1; font-size: 13.5px; color: #334155; line-height: 1.5;">
                        Tap <strong style="color: #0f172a;">"Add"</strong> to confirm. The shortcut will appear on your home screen!
                    </div>
                </div>
            </div>

            <!-- iOS / Safari Instructions (Revealed if on iOS) -->
            <div id="a2hs-ios-steps" style="display: none;">
                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; shrink-0; margin-top: 1px;">1</div>
                    <div style="flex: 1; font-size: 13.5px; color: #334155; line-height: 1.5;">
                        Tap the <strong style="color: #0f172a;">Share icon</strong> (<svg style="display: inline; vertical-align: -2px; width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>) at the bottom of Safari.
                    </div>
                </div>

                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; shrink-0; margin-top: 1px;">2</div>
                    <div style="flex: 1; font-size: 13.5px; color: #334155; line-height: 1.5;">
                        Scroll down and tap <strong style="color: #0f172a;">"Add to Home Screen" (+)</strong>.
                    </div>
                </div>

                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; shrink-0; margin-top: 1px;">3</div>
                    <div style="flex: 1; font-size: 13.5px; color: #334155; line-height: 1.5;">
                        Tap <strong style="color: #0f172a;">"Add"</strong> in the top-right corner to save.
                    </div>
                </div>
            </div>

            <!-- Benefit Callout -->
            <div style="margin-top: 16px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; gap: 10px;">
                <div style="font-size: 18px; shrink-0;">🚀</div>
                <div style="font-size: 12px; color: #64748b; line-height: 1.4;">
                    Opens full screen without URL bar, just like a native mobile app.
                </div>
            </div>
        </div>

        <!-- Footer Action -->
        <div style="padding: 12px 20px 20px; background: #ffffff; text-align: center;">
            <button type="button" onclick="closeA2HSModal()" style="width: 100%; padding: 12px 20px; background: #020617; color: #ffffff; font-weight: 700; font-size: 14px; border: none; border-radius: 12px; cursor: pointer; transition: background 0.15s; box-shadow: 0 4px 12px rgba(2, 6, 23, 0.2);">
                Got it
            </button>
        </div>

    </div>
</div>

<style>
@keyframes a2hsFadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
</style>

<script>
(function() {
    // Store native install prompt event if triggered
    let deferredA2HSPrompt = null;

    window.addEventListener('beforeinstallprompt', function(e) {
        // Prevent default mini-infobar on older mobile Chrome
        e.preventDefault();
        deferredA2HSPrompt = e;
        window.deferredA2HSPrompt = e;
        // Notify any active triggers
        document.querySelectorAll('.a2hs-option, [data-a2hs-trigger]').forEach(function(el) {
            el.setAttribute('data-native-ready', 'true');
        });
    });

    window.addEventListener('appinstalled', function() {
        deferredA2HSPrompt = null;
        window.deferredA2HSPrompt = null;
        closeA2HSModal();
    });

    // Register minimal service worker to enable Chrome shortcut heuristics
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            var swUrl = "{{ asset('sw.js') }}";
            navigator.serviceWorker.register(swUrl).catch(function() {
                // Silently fallback if service workers are disabled or in unsupported local context
            });
        });
    }

    // Main Trigger Function callable anywhere
    window.triggerAddToHomeScreen = async function() {
        // 1. Try native browser prompt if available
        var promptEvent = deferredA2HSPrompt || window.deferredA2HSPrompt;
        if (promptEvent) {
            try {
                promptEvent.prompt();
                var choice = await promptEvent.userChoice;
                if (choice && choice.outcome === 'accepted') {
                    deferredA2HSPrompt = null;
                    window.deferredA2HSPrompt = null;
                    return;
                }
            } catch (err) {
                console.warn('Native install prompt failed, showing instructions:', err);
            }
        }

        // 2. Fallback: Show manual shortcut instructions modal
        openA2HSModal();
    };

    window.openA2HSModal = function() {
        var modal = document.getElementById('a2hs-modal');
        if (!modal) return;

        // Detect platform to show relevant instructions
        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        var androidSteps = document.getElementById('a2hs-android-steps');
        var iosSteps = document.getElementById('a2hs-ios-steps');

        if (isIOS) {
            if (androidSteps) androidSteps.style.display = 'none';
            if (iosSteps) iosSteps.style.display = 'block';
        } else {
            if (androidSteps) androidSteps.style.display = 'block';
            if (iosSteps) iosSteps.style.display = 'none';
        }

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeA2HSModal = function() {
        var modal = document.getElementById('a2hs-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Close on backdrop click
    document.addEventListener('click', function(e) {
        var modal = document.getElementById('a2hs-modal');
        if (modal && e.target === modal) {
            closeA2HSModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeA2HSModal();
        }
    });
})();
</script>
