document.addEventListener('DOMContentLoaded', () =>{
    (() => {
        'use strict';

        const formCostUpdate = document.querySelector('#formCostUpdate');

        formCostUpdate.addEventListener('submit', function(e){
            e.preventDefault();
            const currentBrandId = document.querySelector('.select_brand').value; // получаем ID выбранного бренда
            const fileInput = document.querySelector('#file');
            const checkbox = document.querySelector('.form-check-input');
            let checked = checkbox.checked

            if (fileInput.value.length != 0) {
                const formData = new FormData(this);
                formData.append('fileInput', fileInput);
                formData.append('brandId', currentBrandId);
                formData.append('checkbox', checked);
                costUpdateAjax(formData)
                    .then(data => resMsg(data));
            }
            e.target.reset();
        });

        function costUpdateAjax(data) {
            const uri = '/local/modules/cost_update/inc/cost_update.php';
            return fetch(uri, {
                method: 'POST',
                body: data,
            })
            .then(response => response.json());
        }

        function resMsg(data) {
            const alertSuccess = document.querySelector('.alert-success');
            const alertDanger = document.querySelector('.alert-danger');
            switch(data['event']) {
                case 'success':
                    alertSuccess.innerHTML = data['message'];
                    alertSuccess.style.display = 'block';
                    break;
                case 'error':
                    alertDanger.innerHTML = data['message'];
                    alertDanger.style.display = 'block';
                    break;
            }
            setTimeout(() => { clearAlert() }, 5000);
        }

        function clearAlert() {
            document.querySelectorAll('.alert').forEach(item => {
                item.style.display = 'none';
                item.innerHTML = '';
            });
        }

        
    })(); 
});