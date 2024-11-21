import $ from 'jquery'
import SocialLogin from '@/js/components/SocialLogin'

$(document).ready(function () {
    new SocialLogin().init()

    $('#btnDelete').click(function () {
        $('#modalDelete').modal('show')
    })
})
