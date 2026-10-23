import Swal, { type SweetAlertOptions } from 'sweetalert2'

export default class ModalManager {
    hideModals() {
        Swal.close()
    }

    createError(params: SweetAlertOptions) {
        return Swal.fire({
            icon: 'error',
            showCloseButton: true,
            showCancelButton: false,
            focusCancel: false,
            focusConfirm: true,
            heightAuto: false,
            ...params,
        })
    }

    /**
     * Resolves to true when confirmed, undefined when dismissed.
     */
    createConfirm(params: SweetAlertOptions): Promise<true | undefined> {
        return Swal.fire({
            icon: 'warning',
            showCloseButton: true,
            showCancelButton: true,
            focusCancel: true,
            focusConfirm: false,
            heightAuto: false,
            cancelButtonText: 'Annuler',
            ...params,
        }).then((result) => {
            return result.isConfirmed || undefined
        })
    }
}
