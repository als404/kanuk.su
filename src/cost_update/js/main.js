document.addEventListener('DOMContentLoaded', () =>{
    (() => {
        'use strict';

        const btnCostUpdate = document.querySelector('.btn_update');
        const formSelect = document.querySelector('.select_brand');

        btnCostUpdate.addEventListener('click', () => {
            let currentBrandId = formSelect.value;
            console.dir();
        });
    })(); 
});