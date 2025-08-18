<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Provides the Guestbook form.
 *
 * This form allows users to submit their name, email, phone,
 * feedback, and optional avatar or feedback image.
 * It includes AJAX validation for fields and files.
 */
class GuestbookForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(Connection $database, $entityTypeManager) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   *
   * Creates an instance of the form with injected services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   Returns an instance of the form class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('database'),
        $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'guestbook_form';
  }

  /**
   * Builds the guestbook form.
   *
   * Defines all form fields including name, email,
   * phone, feedback, and file uploads.
   * Adds AJAX validation and submission
   * handlers.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The complete form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['form_messages'] = [
      '#type' => 'markup',
      '#markup' => '<div id="form-messages" style="color:red;margin-bottom:10px;"></div>',
    ];

    // Fields with placeholders.
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ім’я'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Введіть ваше ім’я'),
    ];

    $form['name']['#ajax'] = [
      'callback' => '::validateFieldAjax',
      'event' => 'keyup',
      'wrapper' => 'form-messages',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#placeholder' => $this->t('example@mail.com'),
    ];

    $form['email']['#ajax'] = [
      'callback' => '::validateFieldAjax',
      'event' => 'keyup',
      'wrapper' => 'form-messages',
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Телефон'),
      '#required' => TRUE,
      '#placeholder' => $this->t('0...'),
    ];

    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Відгук'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Ваш відгук...'),
    ];

    // Add #ajax for phone and feedback.
    $form['phone']['#ajax'] = [
      'callback' => '::validateFieldAjax',
      'event' => 'keyup',
      'wrapper' => 'form-messages',
    ];

    $form['feedback']['#ajax'] = [
      'callback' => '::validateFieldAjax',
      'event' => 'keyup',
      'wrapper' => 'form-messages',
    ];

    // Avatar Field with Ajax-cheking.
    $form['avatar'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Аватар'),
      '#upload_location' => 'public://avatars/',
      '#upload_validators' => [
        'FileExtension' => ['jpg', 'jpeg', 'png'],
        'FileSizeLimit' => 2 * 1024 * 1024,
      ],
      '#required' => FALSE,
      '#ajax' => [
        'callback' => '::validateFileAjax',
        'event' => 'change',
        'wrapper' => 'avatar-ajax-wrapper',
      ],
      '#prefix' => '<div id="avatar-ajax-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['avatar_error'] = [
      '#type' => 'markup',
      '#markup' => '<div id="avatar-error" style="color:red;margin-top:5px;"></div>',
    ];

    // Feedback image.
    $form['feedback_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Картинка до відгуку'),
      '#upload_location' => 'public://feedback_images/',
      '#upload_validators' => [
        'FileExtension' => ['jpg', 'jpeg', 'png'],
        'FileSizeLimit' => 5 * 1024 * 1024,
      ],
      '#required' => FALSE,
      '#ajax' => [
        'callback' => '::validateFileAjax',
        'event' => 'change',
        'wrapper' => 'feedback-image-ajax-wrapper',
      ],
      '#prefix' => '<div id="feedback-image-ajax-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['feedback_image_error'] = [
      '#type' => 'markup',
      '#markup' => '<div id="feedback-image-error" style="color:red;margin-top:5px;"></div>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Відправити'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'guestbook-form-wrapper',
      ],
    ];

    $form['#prefix'] = '<div id="guestbook-form-wrapper">';
    $form['#suffix'] = '</div>';

    return $form;
  }

  /**
   * Validates the guestbook form fields.
   *
   * Checks name, email, phone, and file uploads for correctness.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Name.
    if (mb_strlen(trim($form_state->getValue('name'))) < 2) {
      $form_state->setErrorByName('name', $this->t('Ім’я має бути мінімум 2 символи.'));
    }

    // Email.
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Невірний формат email.'));
    }

    // Phone.
    if (!preg_match('/^[0-9]+$/', $form_state->getValue('phone'))) {
      $form_state->setErrorByName('phone', $this->t('Телефон може містити лише цифри.'));
      if (!preg_match('/^[0-9]{10}$/', trim($form_state->getValue('phone')))) {
        $form_state->setErrorByName('phone', $this->t('Телефон може містити лише 10 цифер.'));
      }
    }

    // File validation.
    $this->validateFileServerSide('avatar', $form_state);
    $this->validateFileServerSide('feedback_image', $form_state);
  }

  /**
   * Validates the uploaded file on the server side.
   *
   * Checks the file extension and file size for the given field,
   * and sets an error on the form state if the file is invalid.
   *
   * @param string $field_name
   *   The form field name for the uploaded file.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  private function validateFileServerSide($field_name, FormStateInterface $form_state) {

    $fids = $form_state->getValue($field_name);
    if (!empty($fids) && is_array($fids)) {
      $file_storage = $this->entityTypeManager->getStorage('file');
      $file = $file_storage->load(reset($file_ids));
      if ($file) {
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $size = $file->getSize();
        $max_size = $field_name === 'avatar' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) {
          $form_state->setErrorByName($field_name, $this->t('@field: недопустимий формат файлу.', ['@field' => $field_name]));
        }
        if ($size > $max_size) {
          $form_state->setErrorByName($field_name, $this->t('
          @field: файл перевищує @size МБ.',
          [
            '@field' => $field_name,
            '@size' => $max_size / (1024 * 1024),
          ]));
        }
      }
    }
  }

  /**
   * Handles form submission.
   *
   * Inserts or updates guestbook entries and saves uploaded files.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $avatar_uri = $this->handleFile($form_state->getValue('avatar'));
    $feedback_image_uri = $this->handleFile($form_state->getValue('feedback_image'));

    // Take id, if it's edition.
    $id = $form_state->get('id');

    if ($id) {
      // Update record.
      $this->database->update('guestbook_entries')
        ->fields([
          'name' => trim($form_state->getValue('name')),
          'email' => trim($form_state->getValue('email')),
          'phone' => trim($form_state->getValue('phone')),
          'feedback' => trim($form_state->getValue('feedback')),
          'avatar' => $avatar_uri,
          'feedback_image' => $feedback_image_uri,
        ])
        ->condition('id', $id)
        ->execute();
    }
    else {
      // Paste a new record.
      $this->database->insert('guestbook_entries')
        ->fields([
          'name' => trim($form_state->getValue('name')),
          'email' => trim($form_state->getValue('email')),
          'phone' => trim($form_state->getValue('phone')),
          'feedback' => trim($form_state->getValue('feedback')),
          'avatar' => $avatar_uri,
          'feedback_image' => $feedback_image_uri,
          'created' => $this->time()->getRequestTime(),
        ])
        ->execute();
    }
  }

  /**
   * Saves an uploaded file permanently and returns its URI.
   *
   * @param array|null $fid
   *   The file ID array from the managed_file field.
   *
   * @return string|null
   *   Returns the file URI if saved, NULL otherwise.
   */
  private function handleFile($fid) {
    if (!empty($fid) && is_array($fid)) {
      $file_storage = $this->entityTypeManager->getStorage('file');
      $file = $file_storage->load(reset($file_ids));
      if ($file) {
        $file->setPermanent();
        $file->save();
        return $file->getFileUri();
      }
    }
    return NULL;
  }

  /**
   * Validates uploaded files via AJAX.
   *
   * Checks file type and size, and returns an AjaxResponse with error messages.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response containing validation messages.
   */
  public function validateFileAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $name = $triggering_element['#name'];

    $file_ids = $form_state->getValue($name);
    $response = new AjaxResponse();
    $error_markup = '';

    if (!empty($file_ids) && is_array($file_ids)) {
      $file_storage = $this->entityTypeManager->getStorage('file');
      $file = $file_storage->load(reset($file_ids));
      if ($file) {
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $max_size = ($name === 'avatar') ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) {
          $error_markup = $this->t('Недопустимий формат файлу. Дозволені: jpg, jpeg, png.');
        }
        elseif ($file->getSize() > $max_size) {
          $error_markup = $this->t('Файл перевищує максимальний розмір @size МБ.', ['@size' => $max_size / (1024 * 1024)]);
        }
      }
    }

    $wrapper_id = ($name === 'avatar') ? 'avatar-error' : 'feedback-image-error';
    $response->addCommand(new HtmlCommand("#$wrapper_id", $error_markup));
    return $response;
  }

  /**
   * Handles AJAX submission of the form.
   *
   * Displays either validation errors or a success
   * message without reloading the page.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response for updating the page.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      $messages = [
        '#type' => 'status_messages',
      ];
      $messages_rendered = $this->service('renderer')->renderRoot($messages);
      $response->addCommand(new HtmlCommand('#form-messages', $messages_rendered));

      $form_rendered = $this->service('renderer')->renderRoot($form);
      $response->addCommand(new HtmlCommand('#guestbook-form-wrapper', $form_rendered));
    }
    else {
      // Successfully Addition Message.
      $response->addCommand(new HtmlCommand('#guestbook-form-wrapper',
            '<div class="guestbook-success-message" style="color:green;margin-bottom:10px;">' . $this->t('Дані успішно збережено.') . '</div>'
        ));
    }

    return $response;
  }

  /**
   * Validates a single field via AJAX.
   *
   * Checks name, email, phone, and feedback fields and returns errors.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response with validation messages for the specific field.
   */
  public function validateFieldAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $triggering_element = $form_state->getTriggeringElement();
    $name = $triggering_element['#name'];
    $value = $form_state->getValue($name);

    $error_markup = '';

    switch ($name) {
      case 'name':
        if (mb_strlen(trim($value)) < 2) {
          $error_markup = $this->t('Ім’я має бути мінімум 2 символи.');
        }
        break;

      case 'email':
        $value = trim($value);
        if ($value === '') {
          $error_markup = $this->t('Поле Email обов’язкове.');
        }
        elseif (preg_match('/[^\x00-\x7F]/', $value)) {
          // Any non-ASCII characters → non-Latin.
          $error_markup = $this->t('Мова тільки латинська');
        }
        elseif (!strpos($value, '@') || !strpos($value, '.')) {
          $error_markup = $this->t('Мають бути @ і .');
        }
        break;

      case 'phone':
        if (!preg_match('/^[0-9]*$/', trim($value))) {
          $error_markup = $this->t('Телефон може містити лише цифри.');
        }
        break;

      case 'feedback':
        if (mb_strlen(trim($value)) == 0) {
          $error_markup = $this->t('Введіть ваш відгук.');
        }
        break;
    }

    $response->addCommand(new HtmlCommand('#form-messages', $error_markup));
    return $response;
  }

  /**
   * Retrieves guestbook reviews from the database.
   *
   * Loads reviews with associated file URLs and formats the creation date.
   *
   * @return array
   *   An array of guestbook review data.
   */
  public function getGuestbookReviews() {
    $this->logger('guestbook')->notice('getGuestbookReviews() function was called');

    $query = $this->database()->select('guestbook_entries', 'g')
      ->fields('g')
      ->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();

    $this->logger('guestbook')->notice('Query returned @count rows', [
      '@count' => count($results),
    ]);

    $reviews = [];
    $file_url_generator = $this->service('file_url_generator');

    foreach ($results as $entry) {
      $reviews[] = [
        'name' => $entry->name,
        'email' => $entry->email,
        'phone' => $entry->phone,
        'message' => $entry->feedback,
        'avatar' => $entry->avatar ? $file_url_generator->generateAbsoluteString($entry->avatar) : '',
        'image' => $entry->feedback_image ? $file_url_generator->generateAbsoluteString($entry->feedback_image) : '',
        'created' => date('m/d/Y H:i:s', $entry->created),
      ];
    }

    return $reviews;
  }

}
