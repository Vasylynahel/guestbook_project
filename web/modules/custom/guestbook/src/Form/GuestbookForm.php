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

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ім’я'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Телефон'),
      '#required' => TRUE,
    ];

    $form['feedback'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Відгук'),
      '#required' => TRUE,
    ];

    // Поле аватара з лімітом 2MB та Ajax перевіркою
    $form['avatar'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Аватар'),
      '#upload_location' => 'public://avatars/',
      '#upload_validators' => [
        'FileExtension' => ['jpg', 'jpeg', 'png'],
        'FileSizeLimit' => 5 * 1024 * 1024,
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

    // Контейнер для помилок аватара
    $form['avatar_error'] = [
      '#type' => 'markup',
      '#markup' => '<div id="avatar-error" style="color: red; margin-top: 5px;"></div>',
    ];

    // Поле картинки відгуку з лімітом 5MB та Ajax перевіркою
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

    // Контейнер для помилок картинки відгуку
    $form['feedback_image_error'] = [
      '#type' => 'markup',
      '#markup' => '<div id="feedback-image-error" style="color: red; margin-top: 5px;"></div>',
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
    if (mb_strlen(trim($form_state->getValue('name'))) < 2) {
      $form_state->setErrorByName('name', $this->t('Ім’я має бути мінімум 2 символи.'));
    }

    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Невірний формат email.'));
    }

    if (!preg_match('/^[0-9]+$/', $form_state->getValue('phone'))) {
      $form_state->setErrorByName('phone', $this->t('Телефон може містити лише цифри.'));
    }

    // Додаткова серверна валідація для файлів (на випадок обходу Ajax)
    $avatar_fids = $form_state->getValue('avatar');
    if (!empty($avatar_fids) && is_array($avatar_fids)) {
      $avatar_file = File::load(reset($avatar_fids));
      if ($avatar_file) {
        $ext = strtolower(pathinfo($avatar_file->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
          $form_state->setErrorByName('avatar', $this->t('Аватар: недопустимий формат файлу.'));
        }
        if ($avatar_file->getSize() > 2 * 1024 * 1024) {
          $form_state->setErrorByName('avatar', $this->t('Аватар: розмір файлу перевищує 2MB.'));
        }
      }
    }

    $feedback_fids = $form_state->getValue('feedback_image');
    if (!empty($feedback_fids) && is_array($feedback_fids)) {
      $feedback_file = File::load(reset($feedback_fids));
      if ($feedback_file) {
        $ext = strtolower(pathinfo($feedback_file->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
          $form_state->setErrorByName('feedback_image', $this->t('Картинка до відгуку: недопустимий формат файлу.'));
        }
        if ($feedback_file->getSize() > 5 * 1024 * 1024) {
          $form_state->setErrorByName('feedback_image', $this->t('Картинка до відгуку: розмір файлу перевищує 5MB.'));
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

    $this->messenger()->addStatus($this->t('Дані успішно збережено.'));
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

  /**
   * Ajax callback для перевірки файлу на валідність.
   */
  public function validateFileAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $name = $triggering_element['#name']; // avatar або feedback_image

    $file_ids = $form_state->getValue($name);
    $response = new AjaxResponse();

    $error_markup = '';

    if (!empty($file_ids) && is_array($file_ids)) {
      $fid = reset($file_ids);
      $file = File::load($fid);

      if ($file) {
        $file_extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($file_extension, $allowed)) {
          $error_markup = $this->t('Недопустимий формат файлу. Дозволені: jpg, jpeg, png.');
        }
        else {
          $size = $file->getSize();
          $max_size = ($name === 'avatar') ? 2 * 1024 * 1024 : 5 * 1024 * 1024;

          if ($size > $max_size) {
            $error_markup = $this->t('Файл перевищує максимальний розмір @size МБ.', ['@size' => $max_size / (1024 * 1024)]);
          }
        }
      }
    }

    $wrapper_id = ($name === 'avatar') ? 'avatar-error' : 'feedback-image-error';

    $response->addCommand(new HtmlCommand("#$wrapper_id", $error_markup));

    return $response;
  }

  /**
   * Ajax callback для сабміту форми.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      // Якщо є помилки - повертаємо форму з помилками
      return $form;
    }
    else {
      // Якщо все добре - можна показати повідомлення або оновити форму
      $this->submitForm($form, $form_state);
      $form_state->setRebuild(FALSE);
      $message = $this->t('Дані успішно збережено.');
      return [
        '#type' => 'markup',
        '#markup' => '<div class="guestbook-success-message">' . $message . '</div>',
      ];
    }
  }

}

