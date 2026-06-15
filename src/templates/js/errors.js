import { goBack } from './helper.js';

$(document).ready(function () {
    
    $('#btn').bind('click', function () {
        goBack();
    });

});