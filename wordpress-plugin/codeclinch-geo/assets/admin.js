(function () {
    function request(action, output) {
        output.classList.add('is-visible');
        output.textContent = 'Working...';

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', window.InterdeftGEO.nonce);

        fetch(window.InterdeftGEO.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString(),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                if (!json.success) {
                    output.textContent = json.data && json.data.message ? json.data.message : 'Request failed.';
                    return;
                }

                output.textContent = JSON.stringify(json.data, null, 2);
            })
            .catch(function (error) {
                output.textContent = error.message || 'Request failed.';
            });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-codeclinch-geo-action]');
        if (!button) {
            return;
        }

        var output = document.querySelector('.codeclinch-geo-ai-output');
        if (!output) {
            return;
        }

        var action = button.getAttribute('data-codeclinch-geo-action');
        if (action === 'test-ai') {
            request('codeclinch_geo_test_ai', output);
        }

        if (action === 'generate-ai') {
            request('codeclinch_geo_generate_ai', output);
        }
    });
})();
