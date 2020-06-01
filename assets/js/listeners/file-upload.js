import bsCustomFileInput from 'bs-custom-file-input';

export default (di) => {
    bsCustomFileInput.destroy();
    bsCustomFileInput.init();
};
