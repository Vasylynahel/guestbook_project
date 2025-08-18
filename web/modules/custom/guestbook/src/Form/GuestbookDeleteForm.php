<?php

namespace Drupal\guestbook\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

class GuestbookDeleteForm extends ConfirmFormBase {

  protected $id;

  public function getFormId() {
    return 'guestbook_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion() {
    return $this->t('Ви впевнені, що хочете видалити цей запис?');
  }

  public function getCancelUrl() {
    return new Url('guestbook.page');
  }

  public function getConfirmText() {
    return $this->t('Видалити');
  }

  public function getCancelText() {
    return $this->t('Скасувати');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = Database::getConnection();
    $connection->delete('guestbook_entries')
      ->condition('id', $this->id)
      ->execute();

    $this->messenger()->addMessage($this->t('Запис видалено.'));
    $form_state->setRedirect('guestbook.page');
  }

}

