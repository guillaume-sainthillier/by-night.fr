import '@/js/jquery-global'
import '@tabler/core/dist/js/tabler.esm'
import 'cookieconsent'

window.cookieconsent.initialise({
    palette: {
        popup: {
            background: '#000',
        },
        button: {
            background: '#3f51b5',
        },
    },
    content: {
        message:
            "En poursuivant votre navigation, vous acceptez l'utilisation de cookies pour vous proposer des services et offres adaptés à vos centres d'intérêt et mesurer la fréquentation de nos services.",
        dismiss: "J'ai compris",
        link: 'En savoir plus',
        href: window.privacyUrl,
    },
})
