<?php

namespace Drupal\guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\guestbook\Form\GuestbookEditForm;

class GuestbookController extends ControllerBase {

  public function content() {
    // Отримуємо відгуки
    $reviews = guestbook_get_reviews();

    $current_user = \Drupal::currentUser();

    // Додаємо кнопки для адміністратора
    foreach ($reviews as &$review) {
      if ($current_user->hasPermission('administer site configuration') && !empty($review['id'])) {
        $review['admin_buttons'] = [
          'edit' => Link::createFromRoute('Edit', 'guestbook.edit', ['id' => $review['id']])->toRenderable(),
          'delete' => Link::createFromRoute('Delete', 'guestbook.delete', ['id' => $review['id']])->toRenderable(),
        ];
      }
    }

    return [
      '#type' => 'container',
      'form' => [
        '#weight' => 0,
        'content' => \Drupal::formBuilder()->getForm('Drupal\guestbook\Form\GuestbookForm'),
      ],
      'reviews' => [
        '#weight' => 10,
        '#theme' => 'reviews_list',
        '#reviews' => $reviews,
      ],
    ];
  }

  public function edit($id) {
    // Передаємо $id через запит
    \Drupal::request()->query->set('id', $id);
    return \Drupal::formBuilder()->getForm(\Drupal\guestbook\Form\GuestbookEditForm::class);
  }

  public function delete($id) {
    \Drupal::database()->delete('guestbook_entries')
      ->condition('id', $id)
      ->execute();

    $this->messenger()->addStatus($this->t('Review deleted.'));
    return new RedirectResponse('/guestbook');
  }
}

