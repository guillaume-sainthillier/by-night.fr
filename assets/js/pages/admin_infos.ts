import SocialLogin from '@/js/components/SocialLogin'
import type { Page } from '@/js/types'

const initialize: Page = () => {
    new SocialLogin().init()
}

window.App.registerPage('admin_infos', initialize)
