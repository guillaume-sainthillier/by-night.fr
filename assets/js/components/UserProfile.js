export default class UserProfile {
    init() {
        $(function () {
            $('#btnDelete').click(function () {
                $('#modalDelete').modal();
            });
        });
    }
}
