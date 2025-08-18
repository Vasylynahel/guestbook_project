<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

class GuestbookForm extends FormBase implements ContainerInjectionInterface {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() {
    return 'guestbook_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['form_messages'] = [
      '#type' => 'markup',
      '#markup' => '<div id="form-messages" style="color:red;margin-bottom:10px;"></div>',
    ];


    // Текстові поля з плейсхолдерами
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
      '#placeholder' => $this->t('Тільки цифри'),
    ];

    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Відгук'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Ваш відгук...'),
    ];
    
    // Додати #ajax для phone і feedback
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


    // Поле аватара з Ajax-перевіркою
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

    // Поле картинки до відгуку
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

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Ім’я
    if (mb_strlen(trim($form_state->getValue('name'))) < 2) {
      $form_state->setErrorByName('name', $this->t('Ім’я має бути мінімум 2 символи.'));
    }

    // Email
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Невірний формат email.'));
    }

    // Телефон
    if (!preg_match('/^[0-9]+$/', $form_state->getValue('phone'))) {
      $form_state->setErrorByName('phone', $this->t('Телефон може містити лише цифри.'));
    if (!preg_match('/^[0-9]{10}$/', trim($value))) {
    $error_markup = $this->t('Телефон має містити 10 цифр.');
    }
    }

    // Серверна валідація файлів
    $this->validateFileServerSide('avatar', $form_state);
    $this->validateFileServerSide('feedback_image', $form_state);
  }

  private function validateFileServerSide($field_name, FormStateInterface $form_state) {
    $fids = $form_state->getValue($field_name);
    if (!empty($fids) && is_array($fids)) {
      $file = File::load(reset($fids));
      if ($file) {
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $size = $file->getSize();
        $max_size = $field_name === 'avatar' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) {
          $form_state->setErrorByName($field_name, $this->t('@field: недопустимий формат файлу.', ['@field' => $field_name]));
        }
        if ($size > $max_size) {
          $form_state->setErrorByName($field_name, $this->t('@field: файл перевищує @size МБ.', ['@field' => $field_name, '@size' => $max_size / (1024 * 1024)]));
        }
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $avatar_uri = $this->handleFile($form_state->getValue('avatar'));
    $feedback_image_uri = $this->handleFile($form_state->getValue('feedback_image'));

    $this->database->insert('guestbook_entries')
      ->fields([
        'name' => trim($form_state->getValue('name')),
        'email' => trim($form_state->getValue('email')),
        'phone' => trim($form_state->getValue('phone')),
        'feedback' => trim($form_state->getValue('feedback')),
        'avatar' => $avatar_uri,
        'feedback_image' => $feedback_image_uri,
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  private function handleFile($fid) {
    if (!empty($fid) && is_array($fid)) {
      $file = File::load(reset($fid));
      if ($file) {
        $file->setPermanent();
        $file->save();
        return $file->getFileUri();
      }
    }
    return NULL;
  }

  public function validateFileAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $name = $triggering_element['#name'];

    $file_ids = $form_state->getValue($name);
    $response = new AjaxResponse();
    $error_markup = '';

    if (!empty($file_ids) && is_array($file_ids)) {
      $file = File::load(reset($file_ids));
      if ($file) {
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $max_size = ($name === 'avatar') ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
        $allowed = ['jpg','jpeg','png'];
        if (!in_array($ext, $allowed)) {
          $error_markup = $this->t('Недопустимий формат файлу. Дозволені: jpg, jpeg, png.');
        } elseif ($file->getSize() > $max_size) {
          $error_markup = $this->t('Файл перевищує максимальний розмір @size МБ.', ['@size' => $max_size/(1024*1024)]);
        }
      }
    }

    $wrapper_id = ($name === 'avatar') ? 'avatar-error' : 'feedback-image-error';
    $response->addCommand(new HtmlCommand("#$wrapper_id", $error_markup));
    return $response;
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      // Вивід усіх помилок у контейнер #form-messages
      $messages = [
        '#type' => 'status_messages',
      ];
      $messages_rendered = \Drupal::service('renderer')->renderRoot($messages);
      $response->addCommand(new HtmlCommand('#form-messages', $messages_rendered));

      // Перемальовка самої форми
      $form_rendered = \Drupal::service('renderer')->renderRoot($form);
      $response->addCommand(new HtmlCommand('#guestbook-form-wrapper', $form_rendered));
    } else {
      $this->submitForm($form, $form_state);
      $response->addCommand(new HtmlCommand('#guestbook-form-wrapper',
        '<div class="guestbook-success-message" style="color:green;margin-bottom:10px;">'.$this->t('Дані успішно збережено.').'</div>'
      ));
    }

    return $response;
  }
  
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
            } elseif (preg_match('/[^\x00-\x7F]/', $value)) {
                // Будь-які символи поза ASCII → не латинські
                $error_markup = $this->t('Мова тільки латинська');
            } elseif (!strpos($value, '@') || !strpos($value, '.')) {
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
   function guestbook_get_reviews() {
    \Drupal::logger('guestbook')->notice('guestbook_get_reviews() function was called');

    $query = \Drupal::database()->select('guestbook_entries', 'g')
      ->fields('g')
      ->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();

    \Drupal::logger('guestbook')->notice('Query returned @count rows', [
      '@count' => count($results),
    ]);

    $reviews = [];
    $file_url_generator = \Drupal::service('file_url_generator');

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
