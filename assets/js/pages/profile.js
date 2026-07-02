import $ from 'jquery'
import SocialLogin from '@/js/components/SocialLogin'

/** @type {Page} */
function initialize() {
    new SocialLogin().init()

    $('#btnDelete').click(() => {
        $('#modalDelete').modal('show')
    })
}

window.App.registerPage('profile', initialize)
