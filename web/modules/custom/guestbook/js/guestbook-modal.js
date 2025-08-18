document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('delete-modal');
  const backdrop = document.getElementById('modal-backdrop');
  const confirmBtn = document.getElementById('confirm-delete');
  const cancelBtn = document.getElementById('cancel-delete');

  document.querySelectorAll('.delete-link').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const id = btn.dataset.id;
      confirmBtn.dataset.id = id; // передаємо id
      modal.style.display = 'block';
      backdrop.style.display = 'block';
    });
  });

  cancelBtn.addEventListener('click', () => {
    modal.style.display = 'none';
    backdrop.style.display = 'none';
  });

  backdrop.addEventListener('click', () => {
    modal.style.display = 'none';
    backdrop.style.display = 'none';
  });

  confirmBtn.addEventListener('click', e => {
    const id = e.target.dataset.id;
    if (!id) return;

    fetch(`/guestbook/${id}/delete`, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'confirm=1'
    })
    .then(res => res.text())
    .then(() => {
      // Приховуємо модалку
      modal.style.display = 'none';
      backdrop.style.display = 'none';
      // Видаляємо запис з DOM
      const review = document.querySelector(`.review[data-id="${id}"]`);
      if (review) review.remove();
    });
  });
});

