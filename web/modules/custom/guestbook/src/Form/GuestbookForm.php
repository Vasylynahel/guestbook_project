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
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::validateEmailAjax',
        'event' => 'keyup',
        'wrapper' => 'email-message',
        'progress' => ['type' => 'none'],
      ],
      '#attributes' => ['placeholder' => $this->t('for@example.com')],
    ];
    $form['email_message'] = [
      '#type' => 'markup',
      '#markup' => '',
      '#prefix' => '<div id="email-message">',
      '#suffix' => '</div>',
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::validatePhoneAjax',
        'event' => 'keyup',
        'wrapper' => 'phone-message',
        'progress' => ['type' => 'none'],
      ],
      '#attributes' => ['placeholder' => $this->t('+38...')],
    ];
    $form['phone_message'] = [
      '#type' => 'markup',
      '#markup' => '',
      '#prefix' => '<div id="phone-message">',
      '#suffix' => '</div>',
    ];

    
    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Відгук'),
      '#required' => TRUE,
      '#maxlength' => 5000,
      '#attributes' => ['placeholder' => $this->t('Ваш відгук')],
    ];

    $form['avatar'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Аватар'),
      '#upload_location' => 'public://guestbook_avatars/',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png'],
        'file_validate_size' => [2 * 1024 * 1024], // 2MB
      ],
      '#required' => FALSE,
    ];

    $form['feedback_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Картинка до відгуку'),
      '#upload_location' => 'public://guestbook_feedback_images/',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png'],
        'file_validate_size' => [5 * 1024 * 1024], // 5MB
      ],
      '#required' => FALSE,
    ];


    // Area for form messages returned by AJAX.
    $form['ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'guestbook-ajax-messages'],
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
    
        // Відгук
    $feedback = trim($form_state->getValue('feedback'));
    if ($feedback === '') {
      $form_state->setErrorByName('feedback', $this->t('Поле відгуку є обов’язковим.'));
    }

    // Аватар
    $avatar_fid = $form_state->getValue('avatar');
    if (!empty($avatar_fid)) {
      $file = \Drupal\file\Entity\File::load(reset($avatar_fid));
      if ($file) {
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png'])) {
          $form_state->setErrorByName('avatar', $this->t('Аватар повинен бути JPG або PNG файлом.'));
        }
      }
    }

    // Картинка відгуку
    $feedback_image_fid = $form_state->getValue('feedback_image');
    if (!empty($feedback_image_fid)) {
      $file = \Drupal\file\Entity\File::load(reset($feedback_image_fid));
      if ($file) {
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png'])) {
          $form_state->setErrorByName('feedback_image', $this->t('Картинка до відгуку повинна бути JPG або PNG файлом.'));
        }
      }
    }

  }

public function validateEmailAjax(array &$form, FormStateInterface $form_state) {
  $email = $form_state->getValue('email');
  $message = '';

  // Латинські букви + цифри + спецсимволи
  if (!preg_match('/^[A-Za-z0-9@._-]*$/', $email)) {
    $message = '<span style="color:red;">Email має містити лише латинські символи.</span>';
  }
  elseif (strpos($email, '@') === FALSE || strpos($email, '.') === FALSE) {
    $message = '<span style="color:red;">Email має містити "@" і "."</span>';
  }
  else {
    $message = '<span style="color:green;">Email виглядає правильним.</span>';
  }

  $form['email_message']['#markup'] = $message;
  return $form['email_message'];
}

public function validatePhoneAjax(array &$form, FormStateInterface $form_state) {
  $phone = $form_state->getValue('phone');
  $message = '';

  if (!preg_match('/^[0-9]*$/', $phone)) {
    $message = '<span style="color:red;">Телефон може містити лише цифри.</span>';
  }
  else {
    $message = '<span style="color:green;">OK.</span>';
  }

  $form['phone_message']['#markup'] = $message;
  return $form['phone_message'];
}


  /**
   * {@inheritdoc}
   *
   * Save sanitized values to DB using Database API (no raw SQL).
   */
public function submitForm(array &$form, FormStateInterface $form_state) {
  // Отримуємо значення
  $name = trim($form_state->getValue('name'));
  $email = trim($form_state->getValue('email'));
  $phone = trim($form_state->getValue('phone'));
  $feedback = trim($form_state->getValue('feedback'));

  // Обробка файлів
  $avatar_uri = $this->savePermanentFile($form_state->getValue('avatar'));
  $feedback_uri = $this->savePermanentFile($form_state->getValue('feedback_image'));

  // Запис у БД
  $this->database->insert('guestbook_entries')
    ->fields([
      'name' => $name,
      'email' => $email,
      'phone' => $phone,
      'feedback' => $feedback,
      'avatar' => $avatar_uri,
      'feedback_image' => $feedback_uri,
      'created' => \Drupal::time()->getRequestTime(),
    ])
    ->execute();

  // Повідомлення
  $this->messenger->addStatus($this->t('Дані успішно збережено.'));
}

private function savePermanentFile(array $fid = NULL) {
  if (!empty($fid)) {
    $file = \Drupal\file\Entity\File::load(reset($fid));
    if ($file) {
      $file->setPermanent();
      $file->save();
      return $file->getFileUri();
    }
  }
  return NULL;
}


}

