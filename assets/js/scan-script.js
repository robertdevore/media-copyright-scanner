jQuery(document).ready(function ($) {
    let offset = 0;
    let results = [];

    const $progressBar = $('#mcs-progress-bar');
    const $progressText = $('#mcs-progress-text');
    const $resultsTable = $('#mcs-results-table');
    const $downloadCsvButton = $('#mcs-download-csv');
    const $startScanButton = $('#mcs-start-scan');

    function updateProgress(completed, total) {
        const percentage = Math.round((completed / total) * 100);
        $progressBar.val(percentage);
        $progressText.text(`${percentage}%`);
    }

    function appendResults(newResults) {
        const $tbody = $resultsTable.find('tbody');
        newResults.forEach((result) => {
            $tbody.append(`
                <tr>
                    <td><input type="checkbox" class="mcs-checkbox" data-id="${result.id}"></td>
                    <td><a href="${result.media_url}" target="_blank">${result.id}</a></td>
                    <td>${result.filename}</td>
                    <td>${result.title}</td>
                    <td>${result.alt}</td>
                    <td>${result.description}</td>
                    <td>${result.source || 'Unknown'}</td>
                </tr>
            `);
        });
        $resultsTable.show();
    }

    function downloadCsv(data) {
        const csvContent = [
            ['Media ID', 'Filename', 'Title Text', 'Alt Text', 'Description', 'Source'],
            ...data.map((row) => [
                row.id,
                row.filename,
                row.title,
                row.alt,
                row.description,
                row.source || 'Unknown',
            ]),
        ]
            .map((e) => e.join(','))
            .join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute(
            'download',
            `${mcsVars.domain}-media-copyright-scan-${new Date().toISOString()}.csv`
        );
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function runScan() {
        $startScanButton.prop('disabled', true);
        $.post(
            mcsVars.ajaxUrl,
            {
                action: 'mcs_scan_media',
                nonce: mcsVars.nonce,
                offset,
            },
            (response) => {
                if (response.results) {
                    results = results.concat(response.results);
                    appendResults(response.results);
                }

                if (response.hasMore) {
                    offset += 20;
                    updateProgress(offset, 10000); // Assuming 10,000 media for demonstration
                    runScan();
                } else {
                    $progressBar.val(100);
                    $progressText.text('100%');
                    $downloadCsvButton.show();
                }
            }
        );
    }

    $startScanButton.on('click', runScan);

    $downloadCsvButton.on('click', function () {
        downloadCsv(results);
    });

    let safeIds = [];

    // Handle Select All Checkbox
    $('#mcs-select-all').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('#mcs-results-table tbody input[type="checkbox"]').prop('checked', isChecked);
    });

    // Collect Safe Flags
    $('#mcs-save-flags').on('click', function () {
        safeIds = [];
        $('#mcs-results-table tbody input[type="checkbox"]:checked').each(function () {
            safeIds.push($(this).data('id'));
        });

        if (safeIds.length > 0) {
            $.post(
                mcsVars.ajaxUrl,
                {
                    action: 'mcs_flag_safe',
                    nonce: mcsVars.nonce,
                    safe_ids: safeIds,
                },
                function (response) {
                    if (response.success) {
                        alert(response.data.message);
                        $('#mcs-save-flags').hide();
                        // Remove flagged rows
                        $('#mcs-results-table tbody input[type="checkbox"]:checked')
                            .closest('tr')
                            .remove();
                    }
                }
            );
        }
    });

    // Show Save Flags Button When Checkbox is Selected
    $('#mcs-results-table').on('change', 'input[type="checkbox"]', function () {
        if ($('#mcs-results-table tbody input[type="checkbox"]:checked').length > 0) {
            $('#mcs-save-flags').show();
        } else {
            $('#mcs-save-flags').hide();
        }
    });

    $('#mcs-show-safe').on('click', function () {
        $.post(
            mcsVars.ajaxUrl,
            {
                action: 'mcs_get_safe_images',
                nonce: mcsVars.nonce,
            },
            function (response) {
                const $tbody = $resultsTable.find('tbody');
                $tbody.empty(); // Clear existing rows
                if (response.results.length === 0) {
                    alert('No safe images found.');
                } else {
                    appendResults(response.results);
                }
            }
        );
    });
});
