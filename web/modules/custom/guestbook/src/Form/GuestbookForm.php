<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\EmailValidator;
use Drupal\Core\Render\Markup;

/**
 * Guestbook form.
 */
class GuestbookForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidator
   */
  protected $emailValidator;

  /**
   * Construct.
   */
  public function __construct(Connection $database, MessengerInterface $messenger, EmailValidator $email_validator) {
    $this->database = $database;
    $this->messenger = $messenger;
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger'),
      $container->get('email.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'guestbook_form';
  }

  /**
   * Build the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Wrapper to replace via AJAX.
    $form['#prefix'] = '<div id="guestbook-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ім’я'),
      '#required' => TRUE,
      '#maxlength' => 100,
      '#attributes' => ['placeholder' => $this->t('Введіть ім’я')],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Адреса електронної пошти'),
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('you@example.com')],
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Номер телефону'),
      '#required' => TRUE,
      '#maxlength' => 13,
      '#attributes' => ['placeholder' => $this->t('Тільки цифри')],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Відправити'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Sending...'),
        ],
      ],
    ];

    // Area for form messages returned by AJAX.
    $form['ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'guestbook-ajax-messages'],
    ];

    return $form;
  }

  /**
   * AJAX submit callback.
   *
   * Form API will run validateForm() and submitForm() before this callback.
   * We inspect whether there are errors; if so, we re-render the form (errors will show).
   * If no errors — show success message and clear form fields.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are validation errors, re-render the form so errors will be shown.
    if ($form_state->getErrors()) {
      // Replace the form wrapper with rebuilt form (errors included).
      $rendered = \Drupal::service('renderer')->renderRoot($form);
      $response->addCommand(new ReplaceCommand('#guestbook-form-wrapper', $rendered));
      return $response;
    }

    // No validation errors — submission succeeded. Build success message.
    $message = $this->messenger->all();
    // Clear messenger (we will output our own message).
    \Drupal::messenger()->deleteAll();

    // Create a sanitized success message.
    $success = $this->t('Дякуємо! Ваші дані збережено.');

    // Replace the form with a short success fragment.
    $success_html = '<div class="guestbook-success" role="status">' . $success . '</div>';
    $response->addCommand(new ReplaceCommand('#guestbook-form-wrapper', $success_html));

    // Optionally: you can also trigger client-side JS or other commands.

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Name: length 2..100
    $name = trim($form_state->getValue('name'));
    $name_length = mb_strlen($name);
    if ($name_length < 2 || $name_length > 100) {
      $form_state->setErrorByName('name', $this->t('Ім’я має містити від 2 до 100 символів.'));
    }

    // Email: use email validator service
    $email = trim($form_state->getValue('email'));
    if (!$this->emailValidator->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('Невірний формат електронної пошти.'));
    }

    // Phone: digits only, length limit
    $phone = trim($form_state->getValue('phone'));
    // define allowed phone length — наприклад до 15 цифр
    $max_phone_length = 13;
    if (!preg_match('/^[0-9]+$/', $phone)) {
      $form_state->setErrorByName('phone', $this->t('Номер телефону може містити лише цифри.'));
    }
    elseif (mb_strlen($phone) > $max_phone_length) {
      $form_state->setErrorByName('phone', $this->t('Номер телефону не має перевищувати @n символів.', ['@n' => $max_phone_length]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * Save sanitized values to DB using Database API (no raw SQL).
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $phone = $form_state->getValue('phone');

    // Sanitize before saving is not strictly necessary for SQL injection protection
    // because Database API uses placeholders. But trimming is useful.
    $name = trim($name);
    $email = trim($email);
    $phone = trim($phone);

    // Insert via Database API (safe — no direct SQL).
    $this->database->insert('guestbook_entries')
      ->fields([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'created' => \Drupal::time()->getRequestTime(),
      ])->execute();

    // Add a message to messenger (will be shown if page reloads);
    // For AJAX we use our own message in ajaxSubmit replacement.
    $this->messenger->addStatus($this->t('Дані успішно збережено.'));
  }

}

