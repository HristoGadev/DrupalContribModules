<?php

/**
 * @file Creating query builder
 *
 * @copyright   2019 Hristo Gadev
 * @link
 */


/**
 * {@inheritdoc}
 */

namespace Drupal\query_builder\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides the configurations for query builder.
 *
 * @file
 * Creating query builder
 * @copyright 2019 Hristo Gadev
 * @link
 */
class QueryBuilderForm extends FormBase
{

    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('database')
        );
    }

    /**
     * Returns a unique string identifying the form.
     *
     * The returned ID should be a unique string that can be a valid PHP function
     * name, since it's used in hook implementation names such as
     * hook_form_FORM_ID_alter().
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId()
    {
        return 'query_table_form';
    }

    /**
     * Form constructor.
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     * The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $num_table = $form_state->get('num_table');
        $num_join_table = $form_state->get('num_join_table');
        $num_condition_table = $form_state->get('num_condition_table');

        $options = $this->showDatabaseTables($form, $form_state);


        if ($num_table === null) {

            $num_table = 1;
        }
        if ($num_join_table === null) {

            $num_join_table = 1;
        }
        if ($num_condition_table === null) {

            $num_condition_table = 1;
        }

        $form['#attached']['library'][] = 'query_builder/query_builder_style';
        $form['#tree'] = true;

        $form['select_main'] = array(
            '#type' => 'fieldset',
            '#title' => $this->t('Main table'),
            '#prefix' => '<div id="siteSelectMainWrapper" >',
            '#suffix' => '</div>',
        );
        $form['select'] = array(
            '#type' => 'fieldset',
            '#title' => $this->t('Select tables'),
            '#prefix' => '<div id="siteSelectWrapper" >',
            '#suffix' => '</div>',

        );
        $form['join'] = array(
            '#type' => 'fieldset',
            '#title' => $this->t('Queries with join'),
            '#prefix' => '<div id="siteJoinWrapper">',
            '#suffix' => '</div>',
        );
        $form['condition_from'] = array(
            '#type' => 'fieldset',
            '#title' => $this->t('Conditions'),
            '#prefix' => '<div id="siteConditionWrapper">',
            '#suffix' => '</div>',
        );

        for ($i = 0; $i < $num_table; $i++) {
            $num = $i + 1;

            $numRemove = $form_state->get('num_remove');
            $tablesRemove = $form_state->get('array_remove');
            if (in_array($num, $tablesRemove) && $numRemove != null) {
                continue;
            }

            $form_state->set('num', $num);
            $this->selectMainTable($form, $form_state);
            $this->getAllTables($form, $form_state);
            $used_tables = $form_state->get('used_tables');

            $table_new_wrapper = "my-table-wrapper{$num}";

            $form['select']["table_name_wrapper{$num}"] = array(
                '#type' => 'fieldset',
                '#attributes' => [
                    'id' => $table_new_wrapper,
                ],
            );

            $form['select_main']['table_name_main'] = array(
                '#title' => $this->t('Table name'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#description' => $this->t('Select table from database.'),
                '#required' => true,
                '#options' => $options,

                '#ajax' => [
                    'callback' => '::getMainTable',
                    'wrapper' => 'siteSelectWrapper',
                    'event' => 'change',
                    'method' => 'replace',
                ]
            );
            $form['select']["table_name_wrapper{$num}"]["table{$num}"]['table_name'] = array(
                '#prefix' => '<div class="tableSelect" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Table name'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#description' => $this->t('Select table from database.'),
                '#options' => $used_tables,
                '#ajax' => [
                    'callback' => '::getTableColumns',
                    'wrapper' => $table_new_wrapper,
                    'event' => 'change',
                    'method' => 'replace',
                ]
            );

            $this->getMainTableSelect($form, $form_state, $num);
            $database_column = $form_state->get('database_column');

            $form['select']["table_name_wrapper{$num}"]["table{$num}"]['table_column'] = [
                '#prefix' => '<div class="tableSelect" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Table column'),
                '#type' => 'select',
                '#description' => $this->t('Select column from database.'),
                '#options' => $database_column,
                '#ajax' => [
                    'callback' => '::getConditions',
                    'wrapper' => 'siteConditionWrapper',
                    'event' => 'change',
                    'method' => 'replace',
                ]
            ];
            $form_state->set('database_column', []);
            if ($num_table > 1) {
                $buttonName = "buttonRem{$num}";
                $form['select']["table_name_wrapper{$num}"]["table{$num}"]['actions']['remove_table'] = array(
                    '#name' => $buttonName,
                    '#prefix' => '<div class="tableSelectRemove" >',
                    '#suffix' => '</div>',
                    '#type' => 'submit',
                    '#value' => $this->t("Remove field select"),
                    '#submit' => ['::removeCallback'],
                    '#ajax' => array(
                        'callback' => '::addMoreSelectCallback',
                        'wrapper' => 'siteSelectWrapper',
                        'event' => 'click',
                        'method' => 'replace',
                    ),
                );
            }
        }
        $form['select']['actions'] = array(
            '#type' => 'actions',
        );
        $form['select']['actions']['add_table'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add one'),
            '#submit' => ['::addOne'],
            '#ajax' => array(
                'callback' => '::addMoreSelectCallback',
                'wrapper' => 'siteSelectWrapper',
            ),
            '#button_type' => 'default'
        );
        for ($i = 0; $i < $num_join_table; $i++) {
            $num = $i + 1;

            $numRemove = $form_state->get('num_join_remove');
            $tablesRemove = $form_state->get('array_join_remove');

            if (in_array($num, $tablesRemove) && $numRemove != null) {

                continue;
            }

            $join_new_wrapper = "my-join-wrapper{$num}";

            $form['join']["join_wrapper{$num}"] = array(
                '#type' => 'fieldset',
                '#attributes' => [
                    'id' => $join_new_wrapper,
                    'class' => 'table_name_wrappers',
                ]
            );
            $form['join']["join_wrapper{$num}"]["table{$num}"]['join_type'] = array(
                '#prefix' => '<div class="join" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Join type'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#options' => ['LEFT JOIN' => 'LEFT JOIN', 'RIGHT JOIN' => 'RIGHT JOIN', 'INNER JOIN' => 'INNER JOIN'],
            );


            $form['join']["join_wrapper{$num}"]["table{$num}"]['table_name_join'] = array(
                '#prefix' => '<div class="join" >',
                '#suffix' => '</div>',
                '#title' => $this->t('First table name'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#options' => $options,
                '#ajax' => [
                    'callback' => '::getTableJoinForm',
                    'wrapper' => 'siteJoinWrapper',
                    'event' => 'change',
                ]
            );

            $this->getJoinTableColumn($form, $form_state, $num);
            $database_join_column = $form_state->get('database_join_column');

            $form['join']["join_wrapper{$num}"]["table{$num}"]['column_name_join'] = array(
                '#prefix' => '<div class="join" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Column name'),
                '#type' => 'select',
                '#options' => $database_join_column,
                '#ajax' => [
                    'callback' => '::getConditions',
                    'wrapper' => 'siteConditionWrapper',
                    'event' => 'click',
                    'method' => 'replace',
                ]
            );

            $form['join']["join_wrapper{$num}"]["table{$num}"]['condition_join'] = array(
                '#prefix' => '<div class="join" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Condtitions'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#options' => ['=' => '=', '!=' => '!=', '<' => '<', '>' => '>', '<>' => '<>',],

            );

            $form['join']["join_wrapper{$num}"]["table{$num}"]['table_name1_join'] = array(
                '#prefix' => '<div class="join" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Second table name'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#options' => $options,
                '#ajax' => [
                    'callback' => '::getTableJoinForm',
                    'wrapper' => 'siteJoinWrapper',
                    'event' => 'click',
                ]

            );
            $this->getJoinTableColumnSecond($form, $form_state, $num);
            $database_join_column_second = $form_state->get('database_join_column_second');

            $form['join']["join_wrapper{$num}"]["table{$num}"]['column_name1_join'] = array(
                '#prefix' => '<div class="join" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Column name'),
                '#type' => 'select',
                '#options' => $database_join_column_second,
                '#ajax' => [
                    'callback' => '::getConditions',
                    'wrapper' => 'siteConditionWrapper',
                    'event' => 'click',

                ]
            );

            if ($num_join_table > 1) {
                $buttonJoinName = "buttonRem{$num}";
                $form['join']["join_wrapper{$num}"]["table{$num}"]['actions']['remove_table1'] = array(
                    '#name' => $buttonJoinName,
                    '#prefix' => '<div class="joinRemove" >',
                    '#suffix' => '</div>',
                    '#type' => 'submit',
                    '#value' => $this->t("Remove field join"),
                    '#submit' => ['::removeJoinCallback'],
                    '#ajax' => array(
                        'callback' => '::addMoreJoinCallback',
                        'wrapper' => 'siteJoinWrapper',

                    ),
                );
            }

        }
        $form['join']["table{$num}"]['actions'] = array(
            '#type' => 'actions',
        );
        $form['join']['actions']['add_table_join'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add one join'),
            '#submit' => ['::addJoinOne'],
            '#ajax' => array(
                'callback' => '::addMoreJoinCallback',
                'wrapper' => 'siteJoinWrapper',

            ),
            '#button_type' => 'default'
        );
        for ($i = 0; $i < $num_condition_table; $i++) {
            $num = $i + 1;

            $numRemove = $form_state->get('num_condition_remove');
            $tablesRemove = $form_state->get('array_condition_remove');

            if (in_array($num, $tablesRemove) && $numRemove != null) {

                continue;
            }

            $this->getAllTables($form, $form_state);
            $used_tables = $form_state->get('used_tables');

            $condition_new_wrapper = "my-condition-wrapper{$num}";
            $form['condition_from']["condition_wrapper{$num}"] = array(
                '#type' => 'fieldset',
                '#attributes' => [
                    'id' => $condition_new_wrapper,
                    'class' => 'join_wrappers',
                ]
            );

            $form['condition_from']["condition_wrapper{$num}"] ["table{$num}"]['table_name_condition'] = [
                '#prefix' => '<div class="conditions" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Table name'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#description' => $this->t('All using tables in query'),
                '#options' => $used_tables,
                '#ajax' => array(
                    'callback' => '::getConditions',
                    'wrapper' => 'siteConditionWrapper',
                    'event' => 'click',
                ),
            ];

            $this->getConditionColumn($form, $form_state, $num);
            $used_column = $form_state->get('used_columns');

            $form['condition_from']["condition_wrapper{$num}"] ["table{$num}"]['table_column_condition'] = [
                '#prefix' => '<div class="conditions" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Column name'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#description' => $this->t('All using columns in query'),
                '#options' => $used_column,
            ];

            $form['condition_from']["condition_wrapper{$num}"]["table{$num}"]['condition'] = array(
                '#prefix' => '<div class="conditions" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Condition'),
                '#type' => 'select',
                '#empty_value' => '',
                '#empty_option' => '- Select a value -',
                '#description' => $this->t('Operand'),
                '#options' => ['=' => '=', '!=' => '!=', '<' => '<', '>' => '>', '<>' => '<>', 'IS NULL' => 'IS NULL', 'IS NOT NULL' => 'IS NOT NULL'],
                '#ajax' => [
                    'callback' => '::getConditions',
                    'wrapper' => 'siteConditionWrapper',
                    'event' => 'click',
                    'method' => 'replace',
                ]
            );


            $form['condition_from']["condition_wrapper{$num}"]["table{$num}"]['condition_value'] = array(
                '#prefix' => '<div class="conditions" >',
                '#suffix' => '</div>',
                '#title' => $this->t('Value'),
                '#type' => 'textfield',
                '#description' => $this->t('Set value'),

            );
            $form['condition_from']['actions'] = array(
                '#type' => 'actions',
            );

            $form['condition_from']['actions']['add_table_condition'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Add one condition'),
                '#submit' => ['::addConditionOne'],
                '#ajax' => array(
                    'callback' => '::addMoreConditionCallback',
                    'wrapper' => 'siteConditionWrapper',
                    'event' => 'click',
                ),
                '#button_type' => 'default'
            );
            if ($num_condition_table > 1) {
                $buttonConditionName = "buttonRem{$num}";
                $form['condition_from']["condition_wrapper{$num}"]["table{$num}"]['actions']['remove_table1'] = array(
                    '#name' => $buttonConditionName,
                    '#prefix' => '<div class="conditionsRemove" >',
                    '#suffix' => '</div>',
                    '#type' => 'submit',
                    '#value' => $this->t("Remove field condition"),
                    '#submit' => ['::removeConditionCallback'],
                    '#ajax' => array(
                        'callback' => '::addMoreConditionCallback',
                        'wrapper' => 'siteConditionWrapper',

                    ),
                );
            }
        }
        $result_wrapper = 'my-result-wrapper';
        $form['my_result_container'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => $result_wrapper,
            ]
        ];
        $result_query_wrapper = "my-result-query-wrapper";
        if ($form_state->get('result_string') !== null) {
            $form['my_result_container']['my-result-query-wrapper'] = array(
                '#title' => 'Result of the query builder',
                '#type' => 'textarea',
                '#value' => $form_state->get('result_string'),
                '#disabled' => true,
                '#rows' => 20,
                '#attributes' => [
                    'id' => $result_query_wrapper,

                ]
            );
        }

        $form['submit_build'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Build'),
            '#ajax' => array(
                'callback' => '::submitResult',
                'wrapper' => $result_wrapper,
            ),
            '#button_type' => 'default'
        );

        $form['my_result_container']['result'] = [
            '#theme' => 'table',
            '#header' => $form_state->get('result_header'),
            '#rows' => $form_state->get('result_rows'),
        ];


        return $form;
    }

    /**
     * Ajax callback for adding join form
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function addMoreJoinCallback(array &$form, FormStateInterface $form_state)
    {
        return $form['join'];
    }

    /**
     * Ajax callback for adding select table form
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function addMoreSelectCallback(array &$form, FormStateInterface $form_state)
    {
        return $form['select'];
    }

    /**
     * Ajax callback for adding conditions form
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function addMoreConditionCallback(array &$form, FormStateInterface $form_state)
    {
        return $form['condition_from'];
    }


    /**
     * Ajax add new select form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @return array
     *   The form structure.
     */
    public function addOne(array &$form, FormStateInterface $form_state)
    {

        $fields = $form_state->get('num_table');
        if ($fields === null) {
            $fields = 1;
        }
        $add_field = $fields + 1;
        $form_state->set('num_table', $add_field);
        $form_state->setRebuild();


    }

    /**
     * Ajax add new join form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function addJoinOne(array &$form, FormStateInterface $form_state)
    {

        $fields = $form_state->get('num_join_table');
        if ($fields === null) {
            $fields = 1;
        }
        $add_field = $fields + 1;
        $form_state->set('num_join_table', $add_field);

        $form_state->setRebuild();
    }

    /**
     * Ajax add new condition form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function addConditionOne(array &$form, FormStateInterface $form_state)
    {

        $fields = $form_state->get('num_condition_table');
        if ($fields === null) {
            $fields = 1;
        }
        $add_field = $fields + 1;
        $form_state->set('num_condition_table', $add_field);

        $form_state->setRebuild();
    }

    /**
     * Ajax remove select form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function removeCallback(array &$form, FormStateInterface $form_state)
    {
        $element = $form_state->getTriggeringElement();


        $elementName = $element['#name'];
        $num = substr($elementName, -1);

        $form_state->set('num_remove', $num);


        $arrRemove = $form_state->get('array_remove');
        if ($arrRemove === null) {
            $arrRemove = [];
        }
        array_push($arrRemove, $num);

        $form_state->set('array_remove', $arrRemove);


        $form_state->setRebuild();
    }

    /**
     * Ajax remove join form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function removeJoinCallback(array &$form, FormStateInterface $form_state)
    {
        $element = $form_state->getTriggeringElement();


        $elementName = $element['#name'];
        $num = substr($elementName, -1);

        $form_state->set('num_join_remove', $num);


        $arrRemove = $form_state->get('array_join_remove');
        if ($arrRemove === null) {
            $arrRemove = [];
        }
        array_push($arrRemove, $num);

        $form_state->set('array_join_remove', $arrRemove);


        $form_state->setRebuild();
    }

    /**
     * Ajax condition join form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function removeConditionCallback(array &$form, FormStateInterface $form_state)
    {
        $element = $form_state->getTriggeringElement();


        $elementName = $element['#name'];
        $num = substr($elementName, -1);

        $form_state->set('num_condition_remove', $num);


        $arrRemove = $form_state->get('array_condition_remove');
        if ($arrRemove === null) {
            $arrRemove = [];
        }
        array_push($arrRemove, $num);

        $form_state->set('array_condition_remove', $arrRemove);


        $form_state->setRebuild();
    }

    /**
     * Getting all tables from database
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function showDatabaseTables(array &$form, FormStateInterface $form_state)
    {
        $active_databasse = $this->connection->getConnectionOptions()['database'];
        $query = $this->connection->query("SHOW TABLES FROM $active_databasse");
        $result = $query->fetchAll();

        $options = [];

        foreach ($result as $table) {
            $name = "Tables_in_$active_databasse";
            $tableName = $table->$name;
            $options[$tableName] = $tableName;
        }
        $form_state->set('selected_table', $options);

        return $options;
    }


    /**
     * Ajax callback select table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *  The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function getMainTable(array &$form, FormStateInterface $form_state)
    {

        return $form['select'];
    }

    /**
     * Ajax callback for table columns
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function getTableColumns(array &$form, FormStateInterface $form_state)
    {

        return $form['select']["table_name_wrapper{$form_state->get('num')}"];
    }

    /**
     * Ajax callback for join table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function getTableJoinForm(array &$form, FormStateInterface $form_state)
    {
        return $form['join'];
    }

    /**
     * Ajax callback for condition table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function getConditions(array &$form, FormStateInterface $form_state)
    {
        return $form['condition_from'];
    }

    /**
     * Ajax callback for results of the builder
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function submitResult(array &$form, FormStateInterface $form_state)
    {

        return $form['my_result_container'];
    }

    /**
     * Select columns of choosen select table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param int $num
     *  The current number of table
     */
    public function getMainTableSelect(array &$form, FormStateInterface $form_state, $num)
    {

        $options = $form_state->get('selected_table');
        $tableColumn = [];

        $databaseTable = $form_state->getUserInput();
        $table = $databaseTable["select"]["table_name_wrapper{$num}"]["table{$num}"]["table_name"];
        $databaseTable = $options[$table];
        if ($databaseTable !== null) {

            $query = $this->connection->query("SHOW COLUMNS FROM $databaseTable");
            $result = $query->fetchAll();

            foreach ($result as $table) {
                $tableColumn[$table->Field] = $table->Field;
            }

            $form_state->set('database_column', $tableColumn);
            $form_state->setRebuild();
        } else {


            $form_state->setRebuild();
        }
    }

    /**
     * Select columns of choosen join table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     * @param int $num
     *  The current number of table
     */
    private function getJoinTableColumn(array $form, FormStateInterface $form_state, $num)
    {
        $options = $form_state->get('selected_table');
        $tableColumn = [];
        $databaseTableNum = $form_state->getUserInput();
        $tableChoose = $databaseTableNum['join']["join_wrapper{$num}"]["table{$num}"]['table_name_join'];

        $databaseTable = $options[$tableChoose];


        if ($databaseTable !== null) {
            $query = $this->connection->query("SHOW COLUMNS FROM $databaseTable");
            $result = $query->fetchAll();

            foreach ($result as $table) {
                $tableColumn[$table->Field] = $table->Field;
            }

            $form_state->set('database_join_column', $tableColumn);
            $form_state->setRebuild();
        } else {
            $form_state->set('database_join_column', $tableColumn);
            $form_state->setRebuild();
        }

    }

    /**
     * Select columns of choosen condition table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param int $num
     *  The current number of table
     */
    private function getConditionColumn(array $form, FormStateInterface $form_state, $num)
    {
        $options = $form_state->get('selected_table');
        $tableColumn = [];
        $databaseTableNum = $form_state->getUserInput();
        $tableChoose = $databaseTableNum['condition_from']["condition_wrapper{$num}"] ["table{$num}"]['table_name_condition'];

        $databaseTable = $options[$tableChoose];


        if ($databaseTable !== null) {
            $query = $this->connection->query("SHOW COLUMNS FROM $databaseTable");
            $result = $query->fetchAll();

            foreach ($result as $table) {
                $tableColumn[$table->Field] = $table->Field;
            }

            $form_state->set('database_condition_column', $tableColumn);
            $form_state->setRebuild();
        } else {
            $form_state->set('database_join_column', $tableColumn);
            $form_state->setRebuild();
        }

    }

    /**
     * Select columns of choosen join table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     * @param int $num
     *  The current number of table
     */
    private function getJoinTableColumnSecond(array $form, FormStateInterface $form_state, $num)
    {
        $options = $form_state->get('selected_table');
        $tableColumn = [];
        $databaseTableNum = $form_state->getUserInput();
        $tableChoose = $databaseTableNum['join']["join_wrapper{$num}"]["table{$num}"]['table_name1_join'];

        $databaseTable = $options[$tableChoose];


        if ($databaseTable !== null) {
            $query = $this->connection->query("SHOW COLUMNS FROM $databaseTable");
            $result = $query->fetchAll();

            foreach ($result as $table) {
                $tableColumn[$table->Field] = $table->Field;
            }

            $form_state->set('database_join_column_second', $tableColumn);
            $form_state->setRebuild();
        } else {
            $form_state->set('database_join_column_second', $tableColumn);
            $form_state->setRebuild();
        }
    }

    /**
     * Form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getUserInput();
        $table_type = $values['join']["join_wrapper1"]["table1"]['join_type'];
        $table_first_join = $values["join"]["join_wrapper1"]["table1"]["table_name_join"];
        $table_second_join = $values["join"]["join_wrapper1"]["table1"]["table_name1_join"];
        $condition_table = $values["condition_from"]["condition_wrapper1"]["table1"]["table_name_condition"];
        $condition_column = $values["condition_from"]["condition_wrapper1"]["table1"]["table_column_condition"];
        $resultQuery = '';

        $main_table = $values["select_main"]["table_name_main"];
        $query = $this->connection->select("$main_table");
        $queryArr = [
          "table" => $main_table
        ];
        $queryString = strtr("$" . "query =\Drupal:: database()->select('table');" . "\n", $queryArr);
        $resultQuery .= $queryString;

        foreach ($values['select'] as $wrappers) {
            foreach ($wrappers as $wrapper) {
                $table = $wrapper["table_name"];
                $column = $wrapper["table_column"];
                $query->fields("$table", ["$column"]);
                $selectArr = ["table" => "$table", "field" => "$column"];
                $selectString = strtr("$" . "query->addField('table','field');" . "\n", $selectArr);
                $resultQuery .= $selectString;
            }
        }

        if ($table_type !== '' && $table_first_join === '' && $table_second_join !== '') {
            $this->messenger()->addWarning('Please fill first join table');

        }
        if ($table_type !== '' && $table_first_join !== '' && $table_second_join === '') {
            $this->messenger()->addWarning('Please fill second join second table');

        }
        if ($table_type !== '' && $table_first_join === '' && $table_second_join === '') {
            $this->messenger()->addWarning('Please fill all join tables');

        }
        try {
            if ($table_first_join === '' && $table_second_join === '' && $condition_table === '' && $condition_column === '') {
                $this->queryResult($form,$form_state,$query,$resultQuery);
            }
            if ($table_first_join === '' && $table_second_join === '' && $condition_table !== '' && $condition_column !== '') {
              $this->resultConditionForm($values,$query,$resultQuery);
              $this->queryResult($form,$form_state,$query,$resultQuery);
            }
            if ($table_first_join !== '' && $table_second_join !== '' && $condition_table !== '' && $condition_column !== '') {
                foreach ($values['join'] as $wrappers) {
                    foreach ($wrappers as $wrapper) {
                        $join_type = $wrapper["join_type"];
                        $table_first = $wrapper["table_name_join"];
                        $column_first = $wrapper["column_name_join"];
                        $condition_join = $wrapper["condition_join"];
                        $table_second = $wrapper["table_name1_join"];
                        $column_second = $wrapper["column_name1_join"];
                        $query->join(
                            "$table_first", "$table_first",
                            "$table_first" . "." . "$column_first" . "$condition_join" . "$table_second" . "." . "$column_second"
                        );
                        $joinArr = ["joinType" => $join_type, "table" => $table_first, "alias" => $table_first, "table_first" => $table_first, "column_first" => $column_first,
                            "condition_join" => $condition_join, "table_second" => $table_second, "column_second" => $column_second];
                        $joinString = strtr(
                            "$" . "query->join('table','alias','table_first.column_first condition_join table_second.column_second');"
                            . "\n", $joinArr
                        );
                        $resultQuery .= $joinString;
                    }
                }
                $this->resultConditionForm($values,$query,$resultQuery);
                $this->queryResult($form,$form_state,$query,$resultQuery);
            }
        } catch (\Exception $exception) {
            $this->messenger()->addError('Input of the query builder is not correct');
        }
    }

    /**
     * Display the result on page
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     * @param array $result
     *  An associative array containing the resyult of the query.
     * @param string $resultQuery
     * String  of the Drupal query.
     * @param string $resultQuerySQL
     *  String  of Sql query.
     *
     */
    private function displayResult(array $form, FormStateInterface $form_state, $result, $resultQuery)
    {
        $rows = [];
        $header = [];


        foreach ($result as $content) {
            $valuesArr = [];
            foreach ($content as $key => $value) {
                $header[$key] = $key;
                array_push($valuesArr, $value);
            }
            $rows[] = [
                'data' => $valuesArr,
            ];
        }
        $form_state->set('result_string', $resultQuery);
        $form_state->set('result_header', $header);
        $form_state->set('result_rows', $rows);


        $form_state->setRebuild();
    }

    /**
     * Select the main table
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     *
     */
    private function selectMainTable(array $form, FormStateInterface $form_state)
    {
        $options = $form_state->get('selected_table');

        $databaseTableNum = $form_state->getUserInput();
        $tableChoose = $databaseTableNum['select_main']['table_name_main'];

        $databaseTable = $options[$tableChoose];
        if ($databaseTable !== null) {
            $form_state->set('main_table', $databaseTable);
            $form_state->setRebuild();
        } else {
            $form_state->set('main_table', $databaseTable);

            $form_state->setRebuild();
        }
    }

    /**
     * Select all tables and column using in query
     *
     * @param array $form
     *  An associative array containing the structure of the form.
     * @param FormStateInterface $form_state
     *   The current state of the form.
     * @param $num
     *  The current number of table
     */
    private function getAllTables(array $form, FormStateInterface $form_state)
    {
        $values = $form_state->getUserInput();

        $table = $values["select_main"]["table_name_main"];

        $condition_tables = [];
        $condition_columns = [];

        if ($table !== null) {
          foreach ($values as $value) {
            $condition_tables[$table] = $table;
          }
            for ($i = 0; $i < 3; $i++) {
                if ($i === 0) {
                } else if ($i === 1) {
                    $tables = count($values['select']);
                    for ($j = 1; $j < $tables + 1; $j++) {
                        $table = $values["select"]["table_name_wrapper{$j}"]["table{$j}"]["table_name"];
                        $column = $values["select"]["table_name_wrapper{$j}"]["table{$j}"]["table_column"];
                        if ($table !== '') {
                            $condition_tables[$table] = $table;
                            $condition_columns[$column] = $column;
                        }
                    }
                } else {
                    $tables = count($values['join']);
                    for ($j = 1; $j < $tables + 1; $j++) {
                        $table_first = $values["join"]["join_wrapper{$j}"]["table{$j}"]["table_name_join"];
                        $column_first = $values["join"]["join_wrapper{$j}"]["table{$j}"]["column_name_join"];
                        $table_second = $values["join"]["join_wrapper{$j}"]["table{$j}"]["table_name1_join"];
                        $column_second = $values["join"]["join_wrapper{$j}"]["table{$j}"]["column_name1_join"];
                        if ($table_first !== '') {
                            $condition_tables[$table_first] = $table_first;
                            $condition_columns[$column_first] = $column_first;
                            $condition_tables[$table_second] = $table_second;
                            $condition_columns[$column_second] = $column_second;
                        }
                    }
                }
            }
            $form_state->set('used_columns', $condition_columns);
            $form_state->set('used_tables', $condition_tables);
            $form_state->setRebuild();
        } else {
            $form_state->set('used_columns', $condition_columns);
            $form_state->set('used_tables', $condition_tables);
            $form_state->setRebuild();
        }
    }

  private function queryResult($form, $form_state,$query,$resultQuery)
  {
    $query->range(0, 5);
    $queryRange = '$query->range(0,5);' . "\n";
    $resultQuery .= $queryRange;
    $executeQuery = '$query->execute()->fetchAll();' . "\n";
    $resultQuery .= $executeQuery;
    $result = $query->execute()->fetchAll();
    $this->displayResult($form, $form_state, $result, $resultQuery);
  }

  private function resultConditionForm($values,$query,$resultQuery)
  {
    foreach ($values['condition_from'] as $wrappers) {
      foreach ($wrappers as $wrapper) {
        $table_condition = $wrapper["table_name_condition"];
        $column_condition = $wrapper["table_column_condition"];
        $conditions = $wrapper["condition"];
        $condition_value = $wrapper["condition_value"];
        $conditionArray = ["table" => $table_condition, "field" => $column_condition, "conditions" => $condition_value, "operator" => $conditions];

        if ($conditions === 'IS NULL' || $conditions === 'IS NOT NULL') {
          $query->condition("$table_condition" . "." . "$column_condition", "$conditions");
          $conditionQuery = strtr("$" . "query->condition('table.field','operator');" . "\n", $conditionArray);
          $resultQuery .= $conditionQuery;
        } else {
          $query->condition("$table_condition" . "." . "$column_condition", "$condition_value", "$conditions");
          $conditionQuery = strtr("$" . "query->condition('table.field','conditions','operator');" . "\n", $conditionArray);
          $resultQuery .= $conditionQuery;
        }
      }
    }
  }
}
