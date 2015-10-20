;
(function ($) {
    $(document).ready(function () {
        $(".failsafe-error-msg .notice-dismiss").on('click', function () {
            var action = 'dismissfserror';
            var nonce = $("#fatalsafe-nonce").val();
            var params = {
                action: action,
                nonce: nonce
            };

            $.post(fatalsafe.ajaxurl,params,function(data){
                console.log(data);
            })

        });
    })
})(jQuery);