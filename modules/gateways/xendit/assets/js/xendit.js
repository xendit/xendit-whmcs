Xendit.setPublishableKey(xenditParam.apiKey);
jQuery(function ($) {
    'use strict';

    /**
     * Object to handle Xendit payment forms.
     */
    var cc_xendit_form = {

        /**
         * Initialize event handlers and UI state.
         */
        init: function () {

            // Update CC page
            if ($('form#frmUpdateCC').length) {
                this.form = $('form#frmUpdateCC');
            }
            if ($('#btnSaveCC').length) {
                this.btnSaveCC = $('#btnSaveCC');
            }

            if ($('.validation').length) {
                this.validation = $('.validation');
            }

            if ($('#inputCardNumber').length) {
                this.inputCardNumber = $('#inputCardNumber');
                this.inputCardNumber.payment('formatCardNumber');
            }
            if ($('#inputCardExpiry').length) {
                this.inputCardExpiry = $('#inputCardExpiry');
                this.inputCardExpiry.payment('formatCardExpiry');
            }
            if ($('#inputCardCVV').length) {
                this.inputCardCVV = $('#inputCardCVV');
                this.inputCardCVV.payment('formatCardCVC');
            }

            // Save cc
            this.btnSaveCC.on('click', this.onSubmit);
            this.form.on('submit', this.onSubmit);

            $(document)
                .on(
                    'change',
                    '#newCardInfo :input',
                    this.onCCFormChange
                )
                .on(
                    'checkout_error',
                    this.clearToken
                )
                .ready(function () {
                    $('body').append('<div class="overlay" style="display: none;"></div>' +
                        '<div id="three-ds-container" style="display: none;">' +
                        '<iframe height="450" width="550" id="sample-inline-frame" name="sample-inline-frame"> </iframe>' +
                        '</div>');

                    $('#three-ds-container').css({
                        'width': '550px',
                        'height': '450px',
                        'line-height': '200px',
                        'position': 'fixed',
                        'top': '25%',
                        'left': '40%',
                        'margin-top': '-100px',
                        'margin-left': '-150px',
                        'background-color': '#ffffff',
                        'border-radius': '5px',
                        'text-align': 'center',
                        'z-index': '9999'
                    });
                });
        },

        isXenditChosen: function () {
            return $('input[name=paymentmethod]:checked').val() == "xendit" && 'new' === $('input#new:checked').val();
        },

        hasToken: function () {
            return 0 < $('input[name="xendit_token"]').length;
        },

        hasError: function () {
            return 0 < $('input[name="xendit_failure_reason"]').length;
        },

        block: function () {
            cc_xendit_form.btnSaveCC.prop('disabled', true);
        },

        unBlock: function () {
            cc_xendit_form.btnSaveCC.prop('disabled', false);
        },

        handleError: function (err) {
            var failure_reason;
            if (typeof err != 'undefined') {
                failure_reason = err.message || err.error_code;
            } else {
                failure_reason = 'We encountered an issue while processing the checkout. Please contact us. Code: 200035';
            }
            cc_xendit_form.validation.html(failure_reason);
            cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_failure_reason' value='" + failure_reason + "'/>");
            return true;
        },

        toggleInputError: function (erred, input) {
            input.parent('.form-group').toggleClass('has-error', erred);
            return input;
        },

        extractMonth: function (date) {
            var expiryArray = date.split("/");
            return String(expiryArray[0]).length === 1 ? '0' + String(expiryArray[0]) : String(expiryArray[0]);
        },

        extractYear: function (date) {
            var expiryArray = date.split("/");
            var fullYear = new Date().getFullYear();
            return String(String(fullYear).substr(0, 2) + expiryArray[1]);
        },

        onSubmit: function (e) {
            e.preventDefault();
            if (cc_xendit_form.hasToken() || cc_xendit_form.hasError()) {
                $.ajax({
                    url: $('input[name=return_url]').val(),
                    method: 'POST',
                    data: cc_xendit_form.form.serialize(),
                    dataType: 'json',
                    success: function (response) {
                        if (!response.error) {
                            cc_xendit_form.form.append('<p class="text-success">Updated successful.</p>')
                        } else {
                            cc_xendit_form.form.append('<p class="text-danger">' + response.message + '</p>')
                        }

                        cc_xendit_form.unBlock();
                    }
                });
            } else {
                cc_xendit_form.block();

                var card = cc_xendit_form.inputCardNumber.val().replace(/\s/g, '');
                var cvn = cc_xendit_form.inputCardCVV.val().replace(/ /g, '');
                var expiry = cc_xendit_form.inputCardExpiry.val().replace(/ /g, '');

                // check if all card details are not empty
                if (!card || !cvn || !expiry) {
                    var err = {
                        message: 'missing card information'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // allow 15 digits for AMEX & 16 digits for others
                if (card.length != 16 && card.length != 15) {
                    var err = {
                        message: 'incorrect number'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // validate card number
                if (!Xendit.card.validateCardNumber(card)) {
                    var err = {
                        message: 'incorrect number'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // validate expiry format MM/YY
                if (expiry.length != 5) {
                    var err = {
                        message: 'invalid expire'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // validate cvc
                if (cvn.length < 3) {
                    var err = {
                        message: 'invalid cvn'
                    }
                    return cc_xendit_form.handleError(err);
                }

                var data = {
                    "card_number": card,
                    "card_exp_month": cc_xendit_form.extractMonth(expiry),
                    "card_exp_year": cc_xendit_form.extractYear(expiry),
                    "card_cvn": cvn,
                    "is_multiple_use": true,
                    "on_behalf_of": false,
                    "currency": xenditParam.currency
                };
                var card_type = cc_xendit_form.getCardType();

                cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_card_number' value='" + data.card_number + "'/>");
                cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_card_exp_month' value='" + data.card_exp_month + "'/>");
                cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_card_exp_year' value='" + data.card_exp_year + "'/>");
                cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_card_cvn' value='" + data.card_cvn + "'/>");
                cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_card_type' value='" + card_type + "'/>");

                Xendit.card.createToken(data, cc_xendit_form.onTokenizationResponse);

                // Prevent form submitting
                return false;
            }
        },

        onCCFormChange: function (e) {
            $('.xendit_cc_hidden_input').remove();
            cc_xendit_form.unBlock();
            cc_xendit_form.validation.html("");

            if (e.target.id === 'inputCardNumber') {
                var cardNumber = cc_xendit_form.inputCardNumber.val().replace(/\s/g, '');
                if (Xendit.card.validateCardNumber(cardNumber)) {
                    var data = {
                        bin: cardNumber.substr(0, 6),
                        amount: xenditParam.amount,
                        currency: xenditParam.currency
                    };
                    Xendit.card.getChargeOption(data, cc_xendit_form.onGetChargeOptionResponse);
                }
            }
        },

        onGetChargeOptionResponse: function (err, res) {
            if (err) {
                // If error, don't disturb checkout flow
                console.log('Unable to retrieve charge option', err);
                return;
            }

            // Quit process if no installment or promotion available
            if (!res.installments && !res.promotions) {
                return;
            }
        },

        onTokenizationResponse: function (err, response) {
            if (err) {
                var failure_reason = err.message;
                if (err.error_code == 'INVALID_USER_ID') {
                    failure_reason = 'Invalid sub-account value. Please check your "On Behalf Of" configuration on XenPlatform option. Code: 100004';
                } else if (err.error_code == 'VALIDATION_ERROR') {
                    failure_reason = 'Please verify that the credit card information is correct. Code: 200003';
                }
                cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_failure_reason' value='" + failure_reason + "'/>");

                cc_xendit_form.form.submit();
                return false;
            }
            var token_id = response.id;
            cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_token' value='" + token_id + "'/>");

            var data = {
                "token_id": token_id
            };

            if (xenditParam.can_use_dynamic_3ds === "1") {
                Xendit.card.threeDSRecommendation(data, cc_xendit_form.on3DSRecommendationResponse);
            } else {
                cc_xendit_form.form.submit();
            }

            // Prevent form submitting
            return false;
        },

        clearToken: function () {
            $('.xendit_cc_hidden_input').remove();
        },

        on3DSRecommendationResponse: function (err, response) {
            if (err) {
                cc_xendit_form.form.submit();
                return false;
            }
            cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_should_3ds' value='" + response.should_3ds + "'/>");
            cc_xendit_form.form.submit();

            return;
        },

        /* getting card type from WC card input class name */
        getCardType: function () {
            var class_names = $('#inputCardNumber').attr('class').split(' ');
            var index = class_names.indexOf('identified');

            if (index > -1) {
                return class_names[index - 1];
            }

            return 'unknown';
        },
    };

    cc_xendit_form.init();
});
