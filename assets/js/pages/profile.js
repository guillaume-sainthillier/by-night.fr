import SocialLogin from '../components/SocialLogin';

$(document).ready(function () {
    new SocialLogin().init();

    $('#btnDelete').click(function () {
        $('#modalDelete').modal('show');
    });
});
