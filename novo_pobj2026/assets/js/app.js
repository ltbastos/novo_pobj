document.querySelectorAll('[data-autosubmit]').forEach((element) => {
    element.addEventListener('change', () => {
        if (element.form) {
            element.form.submit();
        }
    });
});