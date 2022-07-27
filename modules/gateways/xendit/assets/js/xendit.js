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
            if ($('#btnCancel').length) {
                this.btnCancel = $('#btnCancel');
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
            this.btnSaveCC.on('click', this.onBtnSubmit);
            this.form.on('submit', this.onSubmit);

            // Back to payment methods
            this.btnCancel.on('click', this.backToPaymentMethod);
        },

        isAddNewCC: function () {
            return $('input[name=action]').val() == "createcc";
        },

        hasToken: function () {
            return 0 < $('input[name="xendit_token"]').length;
        },

        hasError: function () {
            return 0 < $('input[name="xendit_failure_reason"]').length;
        },

        block: function () {
            if (cc_xendit_form.btnSaveCC.find('.loading-icon').length == 0) {
                cc_xendit_form.btnSaveCC.append('<div class="loading-icon spinner-border spinner-border-sm" role="status"></div>');
            }
            cc_xendit_form.btnSaveCC.prop('disabled', true);
        },

        unBlock: function () {
            cc_xendit_form.btnSaveCC.prop('disabled', false);
            cc_xendit_form.btnSaveCC.find('.loading-icon').remove();
        },

        handleError: function (err) {
            var failure_reason;
            if (typeof err != 'undefined') {
                failure_reason = err.message || err.error_code;
            } else {
                failure_reason = 'We encountered an issue while processing the update card. Please contact us. Code: 200035';
            }
            cc_xendit_form.validation.html(failure_reason);
            cc_xendit_form.form.append("<input type='hidden' class='xendit_cc_hidden_input' name='xendit_failure_reason' value='" + failure_reason + "'/>");
            cc_xendit_form.unBlock();
            return true;
        },

        extractMonth: function (month) {
            return month.toString().length < 2 ? '0' + month.toString() : month.toString();
        },

        onBtnSubmit: function (){
            cc_xendit_form.clearToken();
            cc_xendit_form.form.submit();
        },

        onSubmit: function (e) {
            e.preventDefault();

            if(cc_xendit_form.hasError()){
                return cc_xendit_form.handleError({message: $("input[name=xendit_failure_reason]").val()});
            }

            if (cc_xendit_form.hasToken()) {
                cc_xendit_form.block();
                $.ajax({
                    url: $('input[name=return_url]').val(),
                    method: 'POST',
                    data: cc_xendit_form.form.serialize(),
                    dataType: 'json',
                    success: function (response) {
                        if (!response.error) {
                            var message = cc_xendit_form.isAddNewCC() ? "Payment method added successfully" : "Payment method updated successfully"
                            cc_xendit_form.form.append('<p class="message text-success">' + message + '</p>')
                            cc_xendit_form.backToPaymentMethod();
                        } else {
                            cc_xendit_form.form.append('<p class="message text-danger">' + response.message + '</p>')
                        }

                        cc_xendit_form.unBlock();
                    }
                });
            } else {
                cc_xendit_form.block();

                var card = cc_xendit_form.inputCardNumber.val().replace(/\s/g, '');
                var cvn = cc_xendit_form.inputCardCVV.val().replace(/ /g, '');
                var card_type = $.payment.cardType(card);
                var expiry = cc_xendit_form.inputCardExpiry.payment('cardExpiryVal');

                // check if all card details are not empty
                if (!card || !cvn || !expiry) {
                    var err = {
                        message: 'Missing card information'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // allow 15 digits for AMEX & 16 digits for others
                if (card.length != 16 && card.length != 15) {
                    var err = {
                        message: 'Incorrect number'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // validate card number
                if (!$.payment.validateCardNumber(card)) {
                    var err = {
                        message: 'Incorrect number'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // validate expiry format MM/YY
                if (!$.payment.validateCardExpiry(expiry.month, expiry.year)) {
                    var err = {
                        message: 'Invalid expire'
                    }
                    return cc_xendit_form.handleError(err);
                }

                // validate cvc
                if (!$.payment.validateCardCVC(cvn, card_type)) {
                    var err = {
                        message: 'Invalid cvn'
                    }
                    return cc_xendit_form.handleError(err);
                }

                var data = {
                    "card_number": card,
                    "card_exp_month": cc_xendit_form.extractMonth(expiry.month),
                    "card_exp_year": expiry.year.toString(),
                    "card_cvn": cvn,
                    "is_multiple_use": true,
                    "on_behalf_of": "",
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

        onTokenizationResponse: function (err, response) {
            cc_xendit_form.clearMessage();
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

            if(cc_xendit_form.canUseDynamic3DS()){
                Xendit.card.threeDSRecommendation({'token_id': token_id}, cc_xendit_form.on3DSRecommendationResponse);
            }else{
                Xendit.card.createAuthentication({'token_id': token_id, 'amount': 0}, cc_xendit_form.on3DSAuthenticationResponse);
            }

            // Prevent form submitting
            return false;
        },

        on3DSRecommendationResponse: function(err, response){
            if (err) {
                cc_xendit_form.handleError();
                cc_xendit_form.form.submit();
                return false;
            }

            if(response.should_3ds){
                let data = {'token_id': $("input[name='xendit_token']").val(), 'amount': '0'};
                Xendit.card.createAuthentication(data, cc_xendit_form.on3DSAuthenticationResponse);
                return;
            }else{
                cc_xendit_form.form.append( "<input type='hidden' class='xendit_cc_hidden_input' name='xendit_cc_authentication_status' value='1'/>" );
                cc_xendit_form.form.submit();
                return false;
            }
        },

        on3DSAuthenticationResponse: function(err, response){
            if (err) {
                cc_xendit_form.form.submit();
                return false;
            }

            let ccAuthenticationSuccess = 0;
            if(response.status === 'IN_REVIEW' || response.status === 'CARD_ENROLLED' ){
                $('body').append('<div class="three-ds-overlay" style="display: none;"></div>' +
                    '<div id="three-ds-container" style="display: none;">\n' +
                    '                <iframe height="450" width="550" id="sample-inline-frame" name="sample-inline-frame"> </iframe>\n' +
                    '            </div>');
                window.open(response.payer_authentication_url, 'sample-inline-frame');
                $(".three-ds-overlay").show();
                $("#three-ds-container").show();
                return;
            }else if (response.status === 'APPROVED' || response.status === 'VERIFIED') {
                ccAuthenticationSuccess = 1;
                $(".three-ds-overlay").hide();
                $("#three-ds-container").hide();
            }
            cc_xendit_form.form.append( "<input type='hidden' class='xendit_cc_hidden_input' name='xendit_cc_authentication_status' value='"+ ccAuthenticationSuccess +"'/>" );
            cc_xendit_form.form.submit();
            return;
        },

        canUseDynamic3DS: function (){
            return $("input[name='can_use_dynamic_3ds']").val() == 1;
        },

        clearToken: function () {
            $('.xendit_cc_hidden_input').remove();
        },

        clearMessage: function () {
            $('.validation').html('');
            $('.message').remove();
        },

        getCardType: function () {
            var class_names = $('#inputCardNumber').attr('class').split(' ');
            var index = class_names.indexOf('identified');

            if (index > -1) {
                return class_names[index - 1];
            }

            return 'unknown';
        },

        backToPaymentMethod: function (e) {
            parent.location.href = cc_xendit_form.btnCancel.data("href");
        }
    };

    cc_xendit_form.init();
});
