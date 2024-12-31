<?php

namespace UnrePress\Views;

// No direct access
defined('ABSPATH') or die();

?>

<div class="wrap unrepress">
    <h1><?php esc_html_e('Updating WordPress Core', 'unrepress'); ?></h1>

    <hr class="wp-header-end">

    <section class="updating-core">
        <div class="update-status">
            <p class="status-message"><?php esc_html_e('Starting WordPress core update. This can take a few minutes...', 'unrepress'); ?></p>
            <p style="float: left;">
                <i class="spinner is-active"></i>
            </p>
            <div class="clear"></div>
            <ul class="unrepress-update-log">
            </ul>
            <div class="clear"></div>
            <p class="unrepress-completed-ok" style="display: none;">
                <?php esc_html_e('Core update completed successfully!', 'unrepress'); ?>
                <br/>
                <?php esc_html_e('Redirecting to Updates page in 5 seconds', 'unrepress'); ?>.&nbsp;
                <?php
                // Translators: %s: WordPress about page.
                echo wp_kses(
                    sprintf(
                        __('If you are not redirected automatically, please <a href="%s">click here</a>.', 'unrepress'),
                        esc_url(admin_url('about.php?updated'))
                    ),
                    ['a' => ['href' => []]]
                );
?>
            </p>
        </div>
    </section>
</div>
<script>
    // Ensure unrepress is defined
    /* <![CDATA[ */
    var unrepress = {
        ajaxurl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>'
    };
    /* ]]> */

    document.addEventListener('DOMContentLoaded', function() {
        const statusMessage = document.querySelector('.status-message');
        const spinner = document.querySelector('.spinner');
        const logList = document.querySelector('.unrepress-update-log');
        const completedMsg = document.querySelector('.unrepress-completed-ok');
        let lastMessage = '';
        let shouldPoll = true;
        let retryCount = 0;
        const MAX_RETRIES = 3;

        function addLogMessage(message) {
            // Skip if this message is the same as the last one
            if (message === lastMessage) {
                return;
            }

            const li = document.createElement('li');
            li.textContent = message;
            logList.appendChild(li);
            // Scroll to bottom of log
            logList.scrollTop = logList.scrollHeight;

            // Update last message
            lastMessage = message;

            // Check for emoticons in the message
            if (message === ':(') {
                shouldPoll = false;
                statusMessage.textContent = '<?php esc_html_e('Update failed. Please try again later.', 'unrepress'); ?>';
                spinner.classList.remove('is-active');
            } else if (message === ':/') {
                shouldPoll = false;
                statusMessage.textContent = '<?php esc_html_e('Update failed. Please try again later.', 'unrepress'); ?>';
                spinner.classList.remove('is-active');
            } else if (message === ':)' || message === ';)') {
                shouldPoll = false;
                statusMessage.textContent = '<?php esc_html_e('Process completed.', 'unrepress'); ?>';
                spinner.classList.remove('is-active');

                // Show completed message
                completedMsg.style.display = 'block';

                // Redirect after 5 seconds
                setTimeout(() => {
                    window.location.href = '<?php echo esc_url(admin_url('about.php?updated')); ?>';
                }, 5000);
            }
        }

        function fetchLogFile() {
            if (!shouldPoll) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'unrepress_get_update_log');
            formData.append('_ajax_nonce', '<?php echo wp_create_nonce('unrepress_get_update_log'); ?>');

            fetch(unrepress.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && Array.isArray(data.data.lines)) {
                        // Reset retry count on successful response
                        retryCount = 0;

                        // Process each line in the log array
                        data.data.lines.forEach(line => {
                            if (line.trim()) { // Only process non-empty lines
                                addLogMessage(line.trim());
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching log:', error);
                    retryCount++;

                    if (retryCount >= MAX_RETRIES) {
                        shouldPoll = false;
                        statusMessage.textContent = '<?php esc_html_e('Error checking update status.', 'unrepress'); ?>';
                        spinner.classList.remove('is-active');
                    }
                })
                .finally(() => {
                    // Continue polling every 5 seconds if we should
                    if (shouldPoll) {
                        setTimeout(fetchLogFile, 5000);
                    }
                });
        }

        // Start polling for log updates
        fetchLogFile();

        // Create the data object with nonce to start the update process
        const formDataInit = new FormData();
        formDataInit.append('action', 'unrepress_update_core');
        formDataInit.append('_ajax_nonce', '<?php echo wp_create_nonce('unrepress_update_core'); ?>');
        formDataInit.append('type', 'core');

        // Make the initial AJAX request to start the update
        fetch(unrepress.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formDataInit
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    shouldPoll = false;
                    statusMessage.textContent = '<?php esc_html_e('Failed to start update process.', 'unrepress'); ?>';
                    spinner.classList.remove('is-active');
                }
            })
            .catch(error => {
                console.error('Error starting update:', error);
                shouldPoll = false;
                statusMessage.textContent = '<?php esc_html_e('Failed to start update process.', 'unrepress'); ?>';
                spinner.classList.remove('is-active');
            });
    });
</script>
<style>
    .notice, .update-nag {
        display: none !important;
    }
</style>
