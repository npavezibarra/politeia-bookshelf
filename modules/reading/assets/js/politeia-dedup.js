(function ($) {
        function handleActionClick(event) {
                event.preventDefault();

                var $button = $(event.currentTarget);
                if ($button.hasClass('is-processing')) {
                        return;
                }

                var action = $button.data('action');
                var candidateId = parseInt($button.data('candidate-id'), 10);

                if (!candidateId || !action) {
                        return;
                }

                $button.addClass('is-processing');
                var $row = $button.closest('tr');

                $.ajax({
                        url: PoliteiaDedup.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                                action: 'politeia_dedup_action',
                                nonce: PoliteiaDedup.nonce,
                                candidate_id: candidateId,
                                dedup_action: action
                        }
                })
                        .done(function (response) {
                                if (response && response.success) {
                                        if ($row.length) {
                                                $row.fadeOut(300, function () {
                                                        $(this).remove();
                                                });
                                        }
                                } else if (response && response.data && response.data.message) {
                                        window.alert(response.data.message);
                                } else {
                                        window.alert(PoliteiaDedup.error_message);
                                }
                        })
                        .fail(function () {
                                window.alert(PoliteiaDedup.error_message);
                        })
                        .always(function () {
                                $button.removeClass('is-processing');
                        });
        }

        $(document).on('click', '.dedup-confirm, .dedup-reject', handleActionClick);
})(jQuery);
