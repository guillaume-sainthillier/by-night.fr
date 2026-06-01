import $ from 'jquery'
import SocialLogin from '@/js/components/SocialLogin'

$(document).ready(() => {
    new SocialLogin().init()

    $('#btnDelete').click(() => {
        $('#modalDelete').modal('show')
    })
})
