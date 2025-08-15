<?php

namespace Drupal\guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class GuestbookController extends ControllerBase {

  public function content() {
    // Отримуємо відгуки
    $reviews = guestbook_get_reviews();

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

}

