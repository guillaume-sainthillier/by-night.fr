import SocialLogin from '@/js/components/SocialLogin'

/** @type {Page} */
function initialize() {
    new SocialLogin().init()
}

window.App.registerPage('admin_infos', initialize)
