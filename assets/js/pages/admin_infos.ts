import type { Page } from '@silarhi/app-js'
import SocialLogin from '@/js/components/SocialLogin'

const initialize: Page = () => {
    new SocialLogin().init()
}

window.App.registerPage('admin_infos', initialize)
