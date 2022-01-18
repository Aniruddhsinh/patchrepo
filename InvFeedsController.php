<?php

namespace Drupal\inv_uspr\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class InvFeedsController.
 *
 * Controller routines for block example routes.
 *
 * @package Drupal\inv_uspr\Controller
 */
class InvFeedsController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The InvestisLoginController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack that controls the lifecycle of requests.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * A simple controller method to explain what the block example is about.
   */
  public function description() {

    // \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . t('Process start here.'));.
    $op = inv_uspr_node_creator();

    if (!empty($op)) {

      $content_body = 'Following items have been created/updated.<br><br>';
      $content_body_published = 'Following pages have been published.<br><br>';
      $flag_pr = 0;
      $content_body_published .= '<table border="1" cellpadding="10" cellspacing="" height="100%" width="100%" id="bodyTable"><tr><th>URL</th><th>Published On</th></tr>';

      foreach ($op as $keys => $value) {
        $subject = $value['subject'];
        $content_body .= $value['pr_body'] . '<br/>';

        if ($value['status'] == 1) {

          $content_body_published .= '<tr><td>' . $value['pr_body'] . '</td><td>' . $value['publish_time'] . '</td></tr>';
          // $content_body_published .= 'URL: '.$value['pr_body'].'<br/>';
          // $content_body_published .= 'Published On: '.$value['publish_time'].'<br/><br/><br/>';.
          $flag_pr++;
        }
      }

      $content_body_published .= '</table><br>';

      // Mail send for PR create.
      inv_uspr_mail_send($subject, $content_body);

      // Mail send for PR published.
      if ($flag_pr >= 1) {
        $content_body_published .= 'Total number of pages published: << ' . $flag_pr . ' >> (list of nodes published)<br/>';
        inv_uspr_mail_send('US PR Publishing completed', $content_body_published);
      }
    }
    else {
      $content_body = 'Opps, No items found.';
    }

    // Stop caching cron page.
    \Drupal::service('page_cache_kill_switch')->trigger();
    $content['intro'] = [
      '#markup' => '<p>' . $this->t("<p>Hello,<br><br>$content_body<br><br>Thanks,<br>US PR Notifications</p>") . '</p>',
    ];

    return $content;
  }

  /**
   * Function for get categories listing.
   */
  public function listcategories() {

    $relative_url = Url::fromUserInput('/admin/tools/inv-uspr/addcategories');
    $link = Link::fromTextAndUrl('Add Category', $relative_url);

    // Table header.
    $header = [
      'id' => t('S.No.'),
      'name' => t('Category name'),
      'items' => t('Keywords'),
      'edit' => t('Edit'),
      'delete' => t('Delete'),
    ];

    $rows = [];

    // Get categories listing form db.
    $allcategories = \Drupal::database()->select('uspr_categories', 'uc')
      ->fields('uc')
      ->orderBy('cname', 'ASC');
    $table_sort = $allcategories->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
    $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(50000);
    $details_categories = $pager->execute()->fetchAll();
    $page_limit = 50000;
    $sno = 1;
    $current_page = $this->requestStack->getCurrentRequest()->query->get('page');
    if ($current_page != 0) {
      $page_no = $current_page * $page_limit;
      $sno = $sno + $page_no;
    }

    // Update start here.
    $categories_id_name = [];
    $categories_description = [];
    $categories_description_string = [];
    foreach ($details_categories as $content_cat) {
      $categories_description[$content_cat->cname][] = $content_cat->citems;
      $categories_id_name[$content_cat->tid] = $content_cat->cname;
    }
    foreach ($categories_description as $cat_key => $content_merge) {
      $categories_description_string[$cat_key] = implode('|', $content_merge);
    }
    foreach ($categories_description_string as $key_name => $key_value) {

      $cat_id = array_search($key_name, $categories_id_name);
      $edit_url = Url::fromUserInput('/admin/tools/inv-uspr/edit/' . $cat_id);
      $edit_link = Link::fromTextAndUrl('Edit', $edit_url);
      $del_url = Url::fromUserInput('/admin/tools/inv-uspr/listcategories/delete/' . $cat_id);
      $del_link = Link::fromTextAndUrl('Delete', $del_url);

      $rows[] = [
        'data' => [$sno, $key_name, chunk_split($key_value, 75, "\n"), $edit_link, $del_link],
        'style' => '',
      ];
      $sno++;
    }

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'id' => 'inv-uspr-table',
      ],
    ];

    if (count($details_categories) >= 1) {
      $content['intro'] = [
        '#markup' => '<div class="inv-uspr-listing" ><p>' . $link . '</p>' . t('List of All Keywords in Categories.') . '</div>',
      ];

      $content['intro']['location_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      $content['intro']['pager'] = [
        '#type' => 'pager',
      ];
    }
    else {
      $content['intro'] = [
        '#markup' => '<div class="inv-uspr-listing" ><p>' . $link . '</p>No Categories Found.</div>',
      ];
    }

    return $content;
  }

  /**
   * Function for get misiing categories listing.
   */
  public function missingcategories() {

    $relative_url = Url::fromUserInput('/admin/tools/inv-uspr/addcategories');
    $link = Link::fromTextAndUrl('Add Category', $relative_url);

    // Table header.
    $header = [
      'id' => t('S.No.'),
      'nid' => t('Node ID'),
      'items' => t('Keywords'),
      'publisheddate' => t('Published Time'),
    ];
    $rows = [];

    // Get categories listing form db.
    $allcategories = \Drupal::database()->select('uspr_missing_categories', 'umc')
      ->fields('umc')
      ->orderBy('nid', 'ASC');
    $table_sort = $allcategories->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
    $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(25);
    $details_categories = $pager->execute()->fetchAll();
    $page_limit = 25;
    $sno = 1;
    $current_page = $this->requestStack->getCurrentRequest()->query->get('page');
    if ($current_page != 0) {
      $page_no = $current_page * $page_limit;
      $sno = $sno + $page_no;
    }

    foreach ($details_categories as $content) {

      $content = (array) $content;
      // Row with attributes on the row and some of its cells.
      $node_url = Url::fromUserInput('/node/' . $content['nid']);
      $node_link = Link::fromTextAndUrl($content['nid'], $node_url);
      date_default_timezone_set('UTC');

      $rows[] = [
        'data' => [$sno, $node_link, chunk_split($content['citems'], 75, "\n"), DrupalDateTime::createFromFormat(
            'Y-m-d H:i:s',
            $content['publishdate'],
            'UTC'
          )->format('D, m/d/Y - H:i'),
        ],
      ];
      $sno++;
    }
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'id' => 'inv-uspr-missing-table',
      ],
    ];
    if (count($details_categories) >= 1) {
      $content['intro'] = [
        '#markup' => '<div class="inv-uspr-listing" ><p>' . $link . '</p>' . t('List of All Missing Keywords in Categories.') . '</div>',
      ];

      $content['intro']['location_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      $content['intro']['pager'] = [
        '#type' => 'pager',
      ];
    }
    else {
      $content['intro'] = [
        '#markup' => '<div class="inv-uspr-listing" ><p>' . $link . '</p>No Missing Categories Found.</div>',
      ];
    }

    return $content;
  }

}

/**
 * Function for create node programaticaly .
 */
function inv_uspr_node_creator() {
  set_time_limit(0);
  global $base_url;
  // Get texonomy name list in array = "$texonomy_names"
  /*  $query = \Drupal::entityQuery('taxonomy_term');
  #$query->condition('vid', "tags");
  $tids = $query->execute();
  $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);
  $texonomy_names = array();
  foreach ($terms as $term) {
  $term = (array) $term;
  foreach ($term as $val) {
  $val = (array) $val;
  if (isset($val['name']['x-default']))
  $texonomy_names[$val['tid']['x-default']] = $val['name']['x-default'];
  }
  } */

  // Get texonomy items list in array = "$texonomy_items".
  $query_texonomy_items = \Drupal::database()->select('uspr_categories', 'uc');
  $query_texonomy_items->fields('uc', ['citems']);
  $result_texonomy_items = $query_texonomy_items->execute()->fetchAll();
  $texonomy_items = [];
  foreach ($result_texonomy_items as $result_texonomy_item) {
    $texonomy_items[] = $result_texonomy_item->citems;
  }
  // End texonomy name list in array
  // \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . t('Get PR details from central database.'));.
  $uspr_details = get_uspr_details();
  $config = \Drupal::config('inv_client.settings');

  if (!empty($uspr_details)) {

    $mail_send_array = [];

    foreach ($uspr_details as $uspr_detail) {

      if ($uspr_detail->PRUID != '' && $uspr_detail->Body != '') {

        // Get existing record by matching PRUID.
        $query = \Drupal::database()->select('node', 'n');
        $query->join('node__field_pruid', 'nrfp', 'n.nid = nrfp.entity_id');
        $query->join('node__field_uspr_reason', 'nrfur', 'n.nid = nrfur.entity_id');
        $query->join('node__field_pr_publish_date', 'nrfppd', 'n.nid = nrfppd.entity_id');
        $query->fields('n', ['nid'])
          ->fields('nrfp', ['field_pruid_value'])
          ->fields('nrfur', ['field_uspr_reason_value'])
          ->fields('nrfppd', ['field_pr_publish_date_value'])
          ->condition('nrfp.field_pruid_value', $uspr_detail->PRUID, '=');
        $result = $query->execute()->fetchAll();
        if (empty($result)) {
          if ($uspr_detail->Active == 'True') {
            $node_status = $config->get('uspr_auto_publishing');
          }
          else {
            $node_status = 0;
          }
          // \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . t('Start creating node here.'));
          // ---Categories code start here---.
          $categories = explode('|', $uspr_detail->Categories);
          $categories = array_values($categories);

          // Get taxonomy tid using items.
          $node_categories = get_taxonomy_id($categories);

          // Body categories start here.
          if ($config->get('uspr_body_categories')) {
            $body_categories = [];
            // Get categories from items.
            foreach ($texonomy_items as $texonomy_item) {
              if (stripos($uspr_detail->Body, $texonomy_item) !== FALSE) {
                $body_categories[] = $texonomy_item;
              }
            }
            $body_cat_ids = get_taxonomy_id($body_categories);
            foreach ($body_cat_ids as $body_keys => $body_tid) {
              if (!in_array($body_tid, $node_categories)) {
                $node_categories[] = $body_tid;
              }
            }

            /*          //get categories from name
            foreach ($texonomy_names as $tid => $texonomy_name) {
            if (strpos($uspr_detail->Body, $texonomy_name) !== FALSE) {
            $body_categories[$tid] = $texonomy_name;
            }
            }
            foreach ($body_categories as $keys_tid => $value_tid) {
            if (!in_array($keys_tid, $node_categories)) {
            $node_categories[] = $keys_tid;
            }
            } */
          }
          // ---Categories code end here---.
          $PRPublishDate = DrupalDateTime::createFromFormat(
            'Y-m-d H:i:s',
            $uspr_detail->PRPublishDate,
            'UTC'
          )->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

          $values = [
            'nid' => NULL,
            'type' => 'us_press_release',
            'title' => $uspr_detail->Title,
            'field_uspr_active' => $uspr_detail->Active,
            'body' => [
              'value' => $uspr_detail->Body,
              // 'value' => html_entity_decode($uspr_detail->Body, ENT_QUOTES, "UTF-8"),
              // 'value' => htmlentities($uspr_detail->Body),.
              'format' => 'full_html',
            ],
            // 'status' => $uspr_detail->IsPublished,.
            'status' => $node_status,
            'field_prclientid' => $uspr_detail->PRClientID,
            'field_prclientname' => $uspr_detail->PRCLientName,
            'field_pruid' => $uspr_detail->PRUID,
            'field_uspr_reason' => $uspr_detail->Reason,
            'field_isalertable' => $config->get('isalertable'),
            'field_is_notified' => $uspr_detail->IsNotified,
            'field_is_published' => $uspr_detail->IsPublished,
            'field_modified_time' => DrupalDateTime::createFromFormat(
              'Y-m-d H:i:s',
              $uspr_detail->ModifiedTime,
              'UTC'
            )->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
            'field_creation_time' => DrupalDateTime::createFromFormat(
              'Y-m-d H:i:s',
              $uspr_detail->CreationTime,
              'UTC'
            )->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
            'field_pr_publish_date' => $PRPublishDate,
            'field_uspr_categories' => $node_categories,
            'field_uspr_ticker' => $uspr_detail->Ticker,
            'field_uspr_provider' => $uspr_detail->Provider,
            'field_uspr_headline' => [
              'value' => $uspr_detail->Headline,
              'format' => 'full_html',
            ],
            'field_uspr_storylead' => [
              'value' => $uspr_detail->StoryLead,
              'format' => 'full_html',
            ],
            'field_uspr_description' => [
              'value' => $uspr_detail->Description,
              'format' => 'full_html',
            ],
            'field_uspr_language' => $uspr_detail->Language,
            'field_uspr_rendition' => $uspr_detail->Rendition,
            'field_uspr_css' => $uspr_detail->Css,
            'field_uspr_subjectcodes' => $uspr_detail->SubjectCodes,
            'field_uspr_categories_items' => $uspr_detail->Categories,
          ];
          // Create New Node here.
          $node = \Drupal::service('entity_type.manager')->getStorage('node')->create($values);
          $node->save();
          $publish_time = date("d-m-Y H:i:s");
          // Get node id from node.
          $node_id = $node->get('nid')->value;

          \Drupal::messenger()->addStatus(t('PR node created successfully.'));
          \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . "PR created successfully with Nid:$node_id Title:$uspr_detail->Title and Newsid:$uspr_detail->PRUID");
          // inv_uspr_mail_send("US PR $uspr_detail->Title Publishing completed", "Hello,<br><br>Following pages have been published.<br><br>$base_url/node/$node_id<br><br><b>Publishing Type:</b>Instant<br><b>Publishing scheduled:</b>" . date('Y-m-d H:i:s') . "<br><b>Publishing started:</b>" . $PRPublishDate . "<br><b>Publishing completed:</b>" . $PRPublishDate . "<br><br>Thank you,<br>US PR Notification");.
          $mail_send_array[$node_id] = [
            'subject' => "US PR Published : Items Update - Created/Updated : " . $publish_time,
            'pr_body' => "$base_url/node/$node_id<br />",
            'status' => $node_status,
            'publish_time' => $publish_time,
            'title' => $uspr_detail->Title,
          ];

          // Update node id in central database.
          Database::getConnection('inv_uspr_dev')->update('pr_pressreleases')
            ->fields([
              'node_id' => $node_id,
              'IsNotified' => 1,
            ])
            ->condition('PRUID', $uspr_detail->PRUID)
            ->execute();
          // \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . t('Update nid on central database.'));.

          // Add missing categories.
          set_missing_categories($categories, $node_id, $uspr_detail->PRPublishDate);
        }
        else {

          foreach ($result as $row) {
            if ($row->field_uspr_reason_value != $uspr_detail->Reason && $row->field_pr_publish_date_value != $uspr_detail->PRPublishDate) {

              if ($uspr_detail->Active == 'True') {
                $node_status = $uspr_detail->IsPublished;
              }
              else {
                $node_status = 0;
              }
              // Update existing node here.
              $node = Node::load($row->nid);
              $node->setTitle($uspr_detail->Title);
              $node->set("field_uspr_active", $uspr_detail->Active);
              $node->body->value = $uspr_detail->Body;
              $node->body->format = 'full_html';
              $node->set("status", $node_status);
              $node->set("field_uspr_reason", $uspr_detail->Reason);
              $node->set("field_prclientname", $uspr_detail->PRCLientName);
              $node->set("field_isalertable", $config->get('isalertable'));
              $node->set("field_is_notified", $uspr_detail->IsNotified);
              $node->set("field_is_published", $uspr_detail->IsPublished);
              $node->set("field_modified_time", DrupalDateTime::createFromFormat(
                'Y-m-d H:i:s',
                $uspr_detail->ModifiedTime,
                'UTC'
              )->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
              // $node->set("field_creation_time", gmdate("Y-m-d\TH:i:s", strtotime($uspr_detail->CreationTime)));.
              $node->set("field_pr_publish_date", DrupalDateTime::createFromFormat(
                'Y-m-d H:i:s',
                $uspr_detail->PRPublishDate,
                'UTC'
              )->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
              $node->set("field_uspr_ticker", $uspr_detail->Ticker);
              $node->set("field_uspr_provider", $uspr_detail->Provider);
              $node->set("field_uspr_headline", $uspr_detail->Headline);
              $node->set("field_uspr_storylead", $uspr_detail->StoryLead);
              $node->set("field_uspr_description", $uspr_detail->Description);
              $node->set("field_uspr_language", $uspr_detail->Language);
              $node->set("field_uspr_rendition", $uspr_detail->Rendition);
              $node->set("field_uspr_css", $uspr_detail->Css);
              $node->set("field_uspr_subjectcodes", $uspr_detail->SubjectCodes);
              $node->set("field_uspr_categories_items", $uspr_detail->Categories);
              $node->save();
              $publish_time = date("d-m-Y H:i:s");
              \Drupal::messenger()->addStatus(t('PR Updated Title:%title and nid:%nid', ['%title' => $uspr_detail->Title, '%nid' => $row->nid]));
              \Drupal::logger('inv_uspr')->notice(date("Y-m-d H:i:s") . "\t" . "PR updated successfully with Node Id:$row->nid and Newsid:$uspr_detail->PRUID");

              // inv_uspr_mail_send("US PR $uspr_detail->Title : Items Update - Created/Updated : " . date("Y-m-d H:i:s"), "Hello,<br><br>Following items have been created/updated.<br><br><br>$base_url/node/$row->nid<br><br>Thank you,<br>US PR Notification");.
              $mail_send_array[$row->nid] = [
                'subject' => "US PR Published : Items Update - Created/Updated : " . $publish_time,
                'pr_body' => "$base_url/node/$row->nid<br />",
                'msg' => 'PR Imported Successfully.',
                'status' => $node_status,
                'publish_time' => $publish_time,
                'title' => $uspr_detail->Title,
              ];
            }
            else {
              \Drupal::logger('inv_uspr')->error(date("d-m-Y H:i:s") . "\t" . "Duplicate PR skiped with News ID:$uspr_detail->PRUID and Nid:$row->nid");
            }
          }
        }
      }
      else {
        \Drupal::logger('inv_uspr')->error(date("d-m-Y H:i:s") . "\t" . "PR failed to insert Title:$uspr_detail->Title");
        inv_uspr_mail_send($uspr_detail->Title, "Hello,<br><br>US Press release failed to insert details below.<br><br><b>Title:</b>$uspr_detail->Title<br><b>Status:</b>$uspr_detail->Active<br><b>NewsID:</b>$uspr_detail->PRUID<br><b>Publish Reason:</b>$uspr_detail->Reason<br><br>Thanks,<br>US PR Notifications");
      }
    }
    // \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . t('Process end here.'));.
    return $mail_send_array;
  }
  else {
    \Drupal::logger('INV USPR')->notice(date("Y-m-d H:i:s") . "\t" . t('No Press Release Available.'));
    return $mail_send_array;
  }
}

/**
 * Function for get the details from central database and return in array all new PR.
 */
function get_uspr_details() {

  // Get client id site configuration.
  $config = \Drupal::config('inv_client.settings');
  $client_id = $config->get('uspr_client_id');
  $result = [];

  $queryClient = Database::getConnection('inv_uspr_dev')->select('pr_prclientslist', 'pp');
  $queryClient->fields('pp', ['IsEnabled']);
  $queryClient->condition('SourceClientID', $client_id, "=");
  $resultClient = $queryClient->execute()->fetchField();

  if ($resultClient) {
    $query = Database::getConnection('inv_uspr_dev')->select('pr_pressreleases', 'prp');
    $query->fields('prp');
    $query->condition('PRClientID', $client_id, "=");
    // $query->condition('PRPublishDate', $client_id , "=");// date will be in future.
    $result = $query->execute()->fetchAll();
  }

  return $result;
}

/**
 * Implements hook_mail().
 */
function inv_uspr_mail_send($subject, $body) {

  $config = \Drupal::config('inv_client.settings');
  $mailManager = \Drupal::service('plugin.manager.mail');
  $module = 'inv_uspr';
  $key = 'inv_uspr';
  // $to = \Drupal::currentUser()->getEmail();
  $to = $config->get('uspr_mail_receipt');

  $params['node_title'] = $subject;
  $params['message'] = "Hello,<br><br>$body<br>Thanks,<br>US PR Notifications";


  $langcode = \Drupal::currentUser()->getPreferredLangcode();
  $send = TRUE;
  $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

  if ($result['result'] !== TRUE) {
    \Drupal::messenger()->addError(t('There was a problem sending your message and it was not sent.'));
    \Drupal::logger('INV USPR MAIL')->error(date("Y-m-d H:i:s") . "\t" . "Failed to send mail.");
  }
  else {
    \Drupal::messenger()->addStatus(t('Your message has been sent.'));
    \Drupal::logger('INV USPR MAIL')->notice(date("Y-m-d H:i:s") . "\t" . t('Your message has been sent.'));
  }
}

/**
 * @param $categories
 *
 * @return array
 */
function get_taxonomy_id($categories) {
  $node_categories = [];
  if (!empty($categories)) {
    $query_cat = \Drupal::database()->select('uspr_categories', 'uc');
    $query_cat->distinct();
    $query_cat->fields('uc', ['tid']);
    // $query_cat->condition('citems', db_like($categories) . '%', 'LIKE');.
    $query_cat->condition('citems', ($categories), 'IN');
    $result_array = $query_cat->execute()->fetchAll();
    foreach ($result_array as $val) {
      $node_categories[] = $val->tid;
    }
    $node_categories = array_values($node_categories);
  }
  return $node_categories;
}

/**
 * Get missing categories start here.
 */
function set_missing_categories($categories, $nid, $publishDate) {

  $missing_categories = [];
  $categories_items = [];
  $cat_items = \Drupal::database()->select('uspr_categories', 'uc');
  $cat_items->distinct();
  $cat_items->fields('uc', ['citems']);
  $cat_items_array = $cat_items->execute()->fetchAll();

  foreach ($cat_items_array as $cat_key => $cat_value) {
    $categories_items[$cat_key] = strtolower($cat_value->citems);
  }
  foreach ($categories as $value) {
    if (!in_array(strtolower($value), $categories_items)) {
      $missing_categories[] = strtolower($value);
    }
  }
  if (!empty($missing_categories)) {
    $missing_cat = implode('|', $missing_categories);
    \Drupal::database()->insert('uspr_missing_categories')
      ->fields(['nid', 'citems', 'publishdate'])
      ->values([$nid, $missing_cat, $publishDate])
      ->execute();
  }

  return $missing_categories;
}
