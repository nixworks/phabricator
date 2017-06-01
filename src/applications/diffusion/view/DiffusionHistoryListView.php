<?php

final class DiffusionHistoryListView extends DiffusionHistoryView {

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $viewer = $this->getUser();
    $repository = $drequest->getRepository();

    require_celerity_resource('diffusion-history-css');
    Javelin::initBehavior('phabricator-tooltips');

    $handles = $viewer->loadHandles($this->getRequiredHandlePHIDs());

    $rows = array();
    $ii = 0;
    $cur_date = 0;
    $list = null;
    $header = null;
    $view = array();
    foreach ($this->getHistory() as $history) {
      $epoch = $history->getEpoch();
      $new_date = date('Ymd', $history->getEpoch());
      if ($cur_date != $new_date) {
        if ($list) {
          $view[] = id(new PHUIObjectBoxView())
            ->setHeader($header)
            ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
            ->setObjectList($list);
        }
        $date = ucfirst(
          phabricator_relative_date($history->getEpoch(), $viewer));
        $header = id(new PHUIHeaderView())
          ->setHeader($date);
        $list = id(new PHUIObjectItemListView())
          ->setFlush(true)
          ->addClass('diffusion-history-list');
      }

      if ($epoch) {
        $committed = $viewer->formatShortDateTime($epoch);
      } else {
        $committed = null;
      }

      $data = $history->getCommitData();
      $author_phid = $committer = $committer_phid = null;
      if ($data) {
        $author_phid = $data->getCommitDetail('authorPHID');
        $committer_phid = $data->getCommitDetail('committerPHID');
        $committer = $data->getCommitDetail('committer');
      }

      if ($author_phid && isset($handles[$author_phid])) {
        $author_name = $handles[$author_phid]->renderLink();
        $author_image = $handles[$author_phid]->getImageURI();
      } else {
        $author_name = self::renderName($history->getAuthorName());
        $author_image =
          celerity_get_resource_uri('/rsrc/image/people/user0.png');
      }

      $different_committer = false;
      if ($committer_phid) {
        $different_committer = ($committer_phid != $author_phid);
      } else if ($committer != '') {
        $different_committer = ($committer != $history->getAuthorName());
      }
      if ($different_committer) {
        if ($committer_phid && isset($handles[$committer_phid])) {
          $committer = $handles[$committer_phid]->renderLink();
        } else {
          $committer = self::renderName($committer);
        }
        $author_name = hsprintf('%s / %s', $author_name, $committer);
      }

      // We can show details once the message and change have been imported.
      $partial_import = PhabricatorRepositoryCommit::IMPORTED_MESSAGE |
                        PhabricatorRepositoryCommit::IMPORTED_CHANGE;

      $commit = $history->getCommit();
      if ($commit && $commit->isPartiallyImported($partial_import) && $data) {
        $commit_desc = $history->getSummary();
      } else {
        $commit_desc = phutil_tag('em', array(), pht("Importing\xE2\x80\xA6"));
      }

      $browse_button = $this->linkBrowse(
        $history->getPath(),
        array(
          'commit' => $history->getCommitIdentifier(),
          'branch' => $drequest->getBranch(),
          'type' => $history->getFileType(),
        ),
        true);

      $message = null;
      $commit_link = $repository->getCommitURI(
        $history->getCommitIdentifier());

      $commit_name = $repository->formatCommitName(
        $history->getCommitIdentifier(), $local = true);

      $committed = phabricator_datetime($commit->getEpoch(), $viewer);
      $author_name = phutil_tag(
        'strong',
        array(
          'class' => 'diffusion-history-author-name',
        ),
        $author_name);
      $authored = pht('%s on %s.', $author_name, $committed);

      $commit_tag = id(new PHUITagView())
        ->setName($commit_name)
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_INDIGO)
        ->setSlimShady(true);

      $clippy = null;
      if ($commit) {
        Javelin::initBehavior('phabricator-clipboard-copy');
        $clippy = id(new PHUIButtonView())
          ->setIcon('fa-clipboard')
          ->setHref('#')
          ->setTag('a')
          ->addSigil('has-tooltip')
          ->addSigil('clipboard-copy')
          ->addClass('clipboard-copy')
          ->addClass('mmr')
          ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
          ->setMetadata(
            array(
              'text' => $history->getCommitIdentifier(),
              'tip'   => pht('Copy'),
              'align' => 'N',
              'size'  => 'auto',
            ));
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader($commit_desc)
        ->setHref($commit_link)
        ->setDisabled($commit->isUnreachable())
        ->setDescription($message)
        ->setImageURI($author_image)
        ->addAttribute($commit_tag)
        ->addAttribute($authored)
        ->setSideColumn(array(
          $clippy,
          $browse_button,
        ));

      $list->addItem($item);
      $cur_date = $new_date;
    }


    return $view;
  }

}