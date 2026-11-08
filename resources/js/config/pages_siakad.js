/**
 * Config Page JS
 */

'use strict';

$(function () {
    $('.on-roles-prodi').on('click', function () {
        //alert($(this).data('id'));
        $.ajax({
            data: {sms:$(this).data('id')},
            url: `${baseUrl}role\/prodi`,
            type: 'POST',
            success: function (response) {
                location.reload(); 
            }
        })
    });

})