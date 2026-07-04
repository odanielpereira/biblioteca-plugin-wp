/**
 * Book Manager — Main JavaScript
 */

(function() {

    /* Toggle import history details */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.bm-toggle-details');
        if (btn) {
            var index = btn.getAttribute('data-index');
            var detailRow = document.getElementById('bm-history-detail-' + index);
            if (detailRow) {
                detailRow.style.display = detailRow.style.display === 'none' ? '' : 'none';
            }
        }
    });

    /* Toggle Google API options on CSV import page */
    var googleApiCheckbox = document.getElementById('bm-enable-google-api');
    if (googleApiCheckbox) {
        googleApiCheckbox.addEventListener('change', function() {
            var options = document.getElementById('bm-google-api-options');
            if (options) options.style.display = this.checked ? 'block' : 'none';
        });
    }

    /* Select all checkboxes on export all page */
    var checkAll = document.getElementById('bm-check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            var checks = document.querySelectorAll('.bm-export-check');
            checks.forEach(function(c) { c.checked = checkAll.checked; });
        });
    }

    /* Drag and drop sortable for dynamic fields table (jQuery) */
    if (typeof jQuery !== 'undefined') {
        jQuery('#bm-fields-table tbody').sortable({ handle: '.dashicons-menu' });
        jQuery('#bm-call-number-order').sortable({ handle: '.dashicons-menu' });
    }

    /* Tab switcher for login/register page */
    window.bmSwitchTab = function(tab) {
        var tabs = document.querySelectorAll('.bm-account-tab');
        var panels = document.querySelectorAll('.bm-account-panel');
        tabs.forEach(function(t) { t.classList.remove('active'); });
        panels.forEach(function(p) { p.classList.remove('active'); });
        if (tab === 'login') {
            tabs[0].classList.add('active');
            document.getElementById('bm-panel-login').classList.add('active');
        } else {
            tabs[1].classList.add('active');
            document.getElementById('bm-panel-register').classList.add('active');
        }
    };

    /* Star rating setter for reading log */
    window.bmSetRating = function(rating) {
        document.getElementById('bm_rating_value').value = rating;
        var stars = document.querySelectorAll('#bm-star-rating span');
        stars.forEach(function(star, index) {
            star.style.color = index < rating ? '#ffc107' : '#ccc';
        });
    };

    /* Speech recognition (microphone) for reading log review */
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
        var micBtn = document.getElementById('bm-mic-btn');
        var textarea = document.getElementById('bm_review_textarea');
        if (micBtn && textarea) {
            var recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'pt-BR';
            var isListening = false;

            micBtn.addEventListener('click', function() {
                if (isListening) { recognition.stop(); return; }
                recognition.start();
            });
            recognition.onstart = function() {
                isListening = true;
                micBtn.textContent = '🔴';
                micBtn.style.background = '#dc3545';
                micBtn.style.animation = 'bm-pulse 1s infinite';
            };
            recognition.onend = function() {
                isListening = false;
                micBtn.textContent = '🎤';
                micBtn.style.background = '#111';
                micBtn.style.animation = '';
            };
            recognition.onresult = function(event) {
                var finalTranscript = '';
                for (var i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) finalTranscript += event.results[i][0].transcript;
                }
                if (finalTranscript) textarea.value = (textarea.value + ' ' + finalTranscript).trim();
            };
            recognition.onerror = function() { recognition.stop(); };
        }
    }

    /* Period selectors (global functions) */
    window.bmLoadPeriod = function() { window.location.search = 'bm_period=' + document.getElementById('bm-period-select').value; };
    window.bmLoadPeriodTeacher = window.bmLoadPeriod;
    window.bmLoadPeriodGestor = window.bmLoadPeriod;

    /* Add filter row on CSV export page */
    var addFilterBtn = document.getElementById('bm-add-filter');
    if (addFilterBtn) {
        var filterCount = 1;
        addFilterBtn.addEventListener('click', function() {
            var container = document.getElementById('bm-export-filters');
            var firstRow = container.querySelector('.bm-filter-row');
            var newRow = firstRow.cloneNode(true);
            newRow.innerHTML = newRow.innerHTML.replace(/filters\[0\]/g, 'filters[' + filterCount + ']');
            container.appendChild(newRow);
            filterCount++;
        });
    }

})();