import Swal from 'sweetalert2';

export default class ModalManager {
    constructor(di) {
        this._di = di;
    }

    createLoading() {}

    showLoading() {}

    hideLoading() {}

    hideModals() {
        Swal.close();
    }

    createError(params) {
        params = Object.assign(
            {
                icon: 'error',
                showCloseButton: true,
                showCancelButton: false,
                focusCancel: false,
                focusConfirm: true,
                heightAuto: false,
            },
            params
        );

        return Swal.fire(params);
    }

    createConfirm(params) {
        params = Object.assign(
            {
                icon: 'warning',
                showCloseButton: true,
                showCancelButton: true,
                focusCancel: true,
                focusConfirm: false,
                heightAuto: false,
                cancelButtonText: 'Annuler',
            },
            params
        );

        return Swal.fire(params).then((result) => {
            return result && result.value;
        });
    }
}
