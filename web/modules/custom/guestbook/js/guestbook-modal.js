/*global document */
/*jslint browser:true */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('delete-modal'),
            backdrop = document.getElementById('modal-backdrop'),
            confirmBtn = document.getElementById('confirm-delete'),
            cancelBtn = document.getElementById('cancel-delete'),
            links = document.querySelectorAll('.delete-link'),
            i,
            id,
            xhr;

        function handleDeleteClick(e) {
            e.preventDefault();
            id = this.getAttribute('data-id');
            confirmBtn.setAttribute('data-id', id);
            modal.style.display = 'block';
            backdrop.style.display = 'block';
        }

        for (i = 0; i < links.length; i += 1) {
            links[i].addEventListener('click', handleDeleteClick);
        }

        cancelBtn.addEventListener('click', function () {
            modal.style.display = 'none';
            backdrop.style.display = 'none';
        });

        backdrop.addEventListener('click', function () {
            modal.style.display = 'none';
            backdrop.style.display = 'none';
        });

        confirmBtn.addEventListener('click', function (e) {
            id = e.target.getAttribute('data-id');
            if (!id) {
                return;
            }

            xhr = new XMLHttpRequest();
            xhr.open('POST', '/guestbook/' + id + '/delete', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    modal.style.display = 'none';
                    backdrop.style.display = 'none';
                    var review = document.querySelector('.review[data-id="' + id + '"]');
                    if (review && review.parentNode) {
                        review.parentNode.removeChild(review);
                    }
                }
            };
            xhr.send('confirm=1');
        });
    });
}());

