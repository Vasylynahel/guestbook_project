<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

/**
 * Provides a confirmation form for deleting Guestbook entries.
 */
class GuestbookDeleteForm extends ConfirmFormBase {

  /**
   * The ID of the entry being deleted.
   *
   * @var int|null
   */
  protected $id;

  /**
   * {@inheritdoc}
   *
   * Returns the unique ID of this form.
   *
   * @return string
   *   The form ID.
   */
  public function getFormId() {
    return 'guestbook_delete_form';
  }

  /**
   * {@inheritdoc}
   *
   * Builds the delete confirmation form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int|null $id
   *   The ID of the guestbook entry to delete.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Returns the confirmation question to display.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The confirmation question.
   */
  public function getQuestion() {
    return $this->t('Ви впевнені, що хочете видалити цей запис?');
  }

  /**
   * {@inheritdoc}
   *
   * Returns the URL to return to if the action is canceled.
   *
   * @return \Drupal\Core\Url
   *   The cancel URL.
   */
  public function getCancelUrl() {
    return new Url('guestbook.page');
  }

  /**
   * {@inheritdoc}
   *
   * Returns the text for the confirm button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The confirm button text.
   */
  public function getConfirmText() {
    return $this->t('Видалити');
  }

  /**
   * {@inheritdoc}
   *
   * Returns the text for the cancel button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The cancel button text.
   */
  public function getCancelText() {
    return $this->t('Скасувати');
  }

  /**
   * {@inheritdoc}
   *
   * Handles the submission of the delete confirmation form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $connection = Database::getConnection();
    $connection->delete('guestbook_entries')
      ->condition('id', $this->id)
      ->execute();

    $this->messenger()->addMessage($this->t('Запис видалено.'));
    $form_state->setRedirect('guestbook.page');
  }

}
