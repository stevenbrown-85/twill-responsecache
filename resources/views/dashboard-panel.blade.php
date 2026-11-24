<template id="twill-responsecache-panel">
    <div class="twrc">
        <div class="twrc__text">
            <h3 class="twrc__title">Site cache</h3>
            <p class="twrc__meta">
                @unless($enabled)
                    <strong>Caching is currently disabled.</strong>
                @endunless
                <span data-twrc-status>
                    @if($status['cleared_at'] ?? null)
                        Last cleared {{ \Illuminate\Support\Carbon::parse($status['cleared_at'])->diffForHumans() }}@if($status['cleared_by'] ?? null) by {{ $status['cleared_by'] }}@endif.
                    @else
                        Cleared automatically whenever content is updated.
                    @endif
                </span>
            </p>
        </div>
        <button type="button" class="twrc__button" data-twrc-button>Clear site cache</button>
    </div>
</template>

<style>
    .twrc {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin: 0 0 30px;
        padding: 20px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 2px;
    }
    .twrc__title { font-weight: 600; margin: 0 0 4px; }
    .twrc__meta { color: #8c8c8c; margin: 0; }
    .twrc__meta strong { color: #e61414; font-weight: 600; margin-right: 4px; }
    /* Mirrors Twill's .button.button--validate - Twill scopes those styles to
       its Vue components, so they can't be reused from plain markup. */
    .twrc__button {
        -webkit-appearance: none;
        appearance: none;
        flex-shrink: 0;
        display: inline-block;
        height: 40px;
        margin: 0;
        padding: 0 30px;
        border: 0 none;
        border-radius: 2px;
        background: #1d9f3c;
        color: #fff;
        font: inherit;
        font-size: 1em;
        letter-spacing: inherit;
        line-height: 38px;
        text-align: center;
        white-space: nowrap;
        cursor: pointer;
        outline: none;
        transition: color .2s linear, background-color .2s linear;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    .twrc__button:hover,
    .twrc__button:focus { background: #1a8f36; }
    .twrc__button:focus-visible { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #1d9f3c; }
    .twrc__button:active { background: #16792e; }
    .twrc__button:disabled { background: #e5e5e5; color: #a6a6a6; cursor: default; pointer-events: none; }
    @media (max-width: 600px) {
        .twrc { flex-direction: column; align-items: flex-start; }
    }
</style>

<script>
    (function () {
        var endpoint = @json(route(config('twill.admin_route_name_prefix', 'twill.') . 'responsecache.clear'));
        var token = @json(csrf_token());

        function mount() {
            if (document.querySelector('.twrc')) return;

            var template = document.getElementById('twill-responsecache-panel');
            var target = document.querySelector('.dashboard .container') || document.querySelector('.dashboard');
            if (!template || !target) return;

            var panel = template.content.firstElementChild.cloneNode(true);
            target.insertBefore(panel, target.firstChild);

            var button = panel.querySelector('[data-twrc-button]');
            var status = panel.querySelector('[data-twrc-status]');
            var label = button.textContent;

            button.addEventListener('click', function () {
                button.disabled = true;
                button.textContent = 'Clearing…';

                fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error(response.status);
                        return response.json();
                    })
                    .then(function (data) {
                        status.textContent = 'Last cleared just now' + (data.cleared_by ? ' by ' + data.cleared_by : '') + '.';
                        button.disabled = false;
                        button.textContent = 'Cache cleared ✓';
                        setTimeout(function () { button.textContent = label; }, 2500);
                    })
                    .catch(function (error) {
                        status.textContent = error.message === '403'
                            ? 'You don’t have permission to clear the cache.'
                            : 'Something went wrong clearing the cache. Please try again.';
                        button.textContent = label;
                        button.disabled = false;
                    });
            });
        }

        // Twill's Vue app re-renders the dashboard on mount, so wait for it.
        if (document.readyState === 'complete') {
            setTimeout(mount, 0);
        } else {
            window.addEventListener('load', function () { setTimeout(mount, 0); });
        }
    })();
</script>
