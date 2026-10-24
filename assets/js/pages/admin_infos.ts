import { definePage } from '@silarhi/app-js'
import SocialLogin from '@/js/components/SocialLogin'

export default definePage(() => {
    new SocialLogin().init()
})
