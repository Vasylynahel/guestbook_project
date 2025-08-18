<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Guestbook Edit Form.
 */
class GuestbookEditForm extends FormBase {

  /**
   * {@inheritdoc}
   *
   * Returns a unique ID for this form.
   */
  public function getFormId() {
    return 'guestbook_edit_form';
  }

  /**
   * {@inheritdoc}
   *
   * Builds the Guestbook edit form with all fields.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $id = $this->request()->query->get('id');
    if (!$id) {
      $this->messenger()->addError($this->t('No review ID provided.'));
      return [];
    }

    // Save id for submitForm in form_state.
    $form_state->set('id', $id);

    $record = $this->database()
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

  /**
   * {@inheritdoc}
   *
   * Handles the submission of the Guestbook edit form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->get('id');

    $this->database()
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
