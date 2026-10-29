import { definePage } from '@silarhi/app-js'
import CommentApp from '@/js/components/CommentApp'
import Widgets from '@/js/components/Widgets'

export default definePage(() => {
    new Widgets().init()

    new CommentApp().init()

    // Google Maps loads on demand: the frame replaces the placeholder of the map card
    const loadMap = document.getElementById('loadMap')
    loadMap?.addEventListener('click', () => {
        const frame = document.createElement('iframe')
        frame.src = loadMap.dataset.map ?? ''
        frame.title = 'Plan'
        frame.loading = 'lazy'
        frame.referrerPolicy = 'no-referrer-when-downgrade'
        frame.allowFullscreen = true
        frame.style.border = '0'
        document.getElementById('googleMap')?.replaceChildren(frame)
    })
})
