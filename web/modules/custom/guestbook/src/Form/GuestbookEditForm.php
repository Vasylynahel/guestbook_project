<?php 

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class GuestbookEditForm extends FormBase {

  public function getFormId() {
    return 'guestbook_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $id = \Drupal::request()->query->get('id');
    if (!$id) {
      $this->messenger()->addError($this->t('No review ID provided.'));
      return [];
    }

    // Збережемо id у form_state, щоб submitForm міг його використати
    $form_state->set('id', $id);

    $record = \Drupal::database()
      ->select('guestbook_entries', 'g')
      ->fields('g', ['name', 'feedback'])
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      $this->messenger()->addError($this->t('Review not found.'));
      return [];
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $record['name'],
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#default_value' => $record['feedback'],
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->get('id'); // тепер тут є id ✅

    \Drupal::database()
      ->update('guestbook_entries')
      ->fields([
        'name' => $form_state->getValue('name'),
        'feedback' => $form_state->getValue('message'),
      ])
      ->condition('id', $id)
      ->execute();

    $this->messenger()->addStatus($this->t('Review updated.'));
  }
}

