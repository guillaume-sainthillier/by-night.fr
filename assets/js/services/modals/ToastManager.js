import Swal from 'sweetalert2';

export default class ToastManager {
    createToast(icon, message, params) {
        const Toast = Swal.mixin(
            Object.assign(
                {
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    showCloseButton: true,
                    timer: 3000,
                },
                params || {}
            )
        );

        return Toast.fire({
            icon: icon,
            title: message,
        });
    }
}
