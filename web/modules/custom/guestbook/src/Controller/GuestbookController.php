<?php

namespace Drupal\guestbook\Controller;

use Drupal\guestbook\Form\GuestbookEditForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides controller for Guestbook module.
 */
class GuestbookController extends ControllerBase {

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * GuestbookController constructor.
   */
  public function __construct(AccountProxyInterface $current_user, FormBuilderInterface $form_builder, Connection $database, RequestStack $request_stack) {
    $this->currentUser = $current_user;
    $this->formBuilder = $form_builder;
    $this->database = $database;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('form_builder'),
      $container->get('database'),
      $container->get('request_stack'),
    );
  }

  /**
   * Displays guestbook page with form and reviews.
   *
   * @return array
   *   Render array with form and reviews list.
   */
  public function content() {
    // Load reviews from helper function.
    $reviews = guestbook_get_reviews();

    // Add admin buttons and contact details to reviews.
    foreach ($reviews as &$review) {
      if ($this->currentUser->hasPermission('administer site configuration') && !empty($review['id'])) {
        $review['admin_buttons'] = [
          'edit' => Link::createFromRoute('Edit', 'guestbook.edit', ['id' => $review['id']])->toRenderable(),
          'delete' => Link::createFromRoute('Delete', 'guestbook.delete', ['id' => $review['id']])->toRenderable(),
        ];
      }

      $review['contact'] = [
        'email' => $review['email'] ?? NULL,
        'phone' => $review['phone'] ?? NULL,
      ];
    }

    return [
      '#type' => 'container',
      'form' => [
        '#weight' => 0,
        'content' => $this->formBuilder->getForm('Drupal\guestbook\Form\GuestbookForm'),
      ],
      'reviews' => [
        '#weight' => 10,
        '#theme' => 'reviews_list',
        '#reviews' => $reviews,
      ],
    ];
  }

  /**
   * Displays edit form for a review.
   *
   * @param int $id
   *   Review ID.
   *
   * @return array
   *   Render array with edit form.
   */
  public function edit($id) {
    $this->requestStack->getCurrentRequest()->query->set('id', $id);
    return $this->formBuilder->getForm(GuestbookEditForm::class);
  }

  /**
   * Deletes a review entry.
   *
   * @param int $id
   *   Review ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to guestbook page.
   */
  public function delete($id) {
    $this->database->delete('guestbook_entries')
      ->condition('id', $id)
      ->execute();

    $this->messenger()->addStatus($this->t('Review deleted.'));
    return new RedirectResponse('/guestbook');
  }

}
