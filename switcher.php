<?php
/**
 * 			Plugin Editor Switcher
 * @version			1.7.2
 * @package			Editor Switcher
 * @copyright		Copyright (C) 2007-2012 Joomler!.net. All rights reserved.
 * @license			http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @author			Yoshiki Kozaki(www.joomler.net)
 * @link			http://www.joomler.net/
 *
 */
// no direct access
defined('_JEXEC') or die;

/**
 * Editor Switcher Plugin
 */
class plgEditorSwitcher extends JPlugin
{

	protected $_switchereditor = null;
	protected $cookiename = 'editorswitchercurrent';

	/**
	 * Constructor
	 *
	 * @param  object  $subject  The object to observe
	 * @param  array   $config   An array that holds the plugin configuration
	 *
	 * @since       1.5
	 */
	public function __construct(& $subject, $config)
	{
		$editor = JRequest::getVar($this->cookiename, 'switcher', 'cookie', 'cmd');

		if ($editor == 'switcher')
		{
			$editor = JPluginHelper::getPlugin('editors', 'switcher')->params->get('default_editor', 'none');
		}

		if (file_exists(dirname(dirname(__FILE__)) . '/' . $editor . '/' . $editor . '.php') && JPluginHelper::isEnabled('editors', $editor))
		{
			$plugin = JPluginHelper::getPlugin('editors', $editor);
			$this->_setSwitcherEditor($subject, $config, $plugin);
		}
		else
		{
			$plugins = JPluginHelper::getPlugin('editors');
			$plugin  = null;
			if (count($plugins))
			{
				foreach ($plugins as $v)
				{
					if ($v->name != 'switcher')
					{
						$plugin = $v;
						break;
					}
				}
			}

			if ($plugin)
			{
				$this->_setSwitcherEditor($subject, $config, $plugin);
			}
			else
			{
				JError::raiseWarning('SOME_ERROR_CODE', JText::_('PLG_EDITOR_SWITCHER_EDITORWASNOTFOUND'));
			}
		}

		parent::__construct($subject, $config);
	}

	/**
	 * Create the selected editor
	 *
	 * @param object $subject
	 * @param array $config
	 * @param object $plugin
	 */
	protected function _setSwitcherEditor(& $subject, & $config, $plugin)
	{
		$editor = $plugin->name;

		$config['params'] = $plugin->params;
		$config['name']   = strtolower($editor);
		$config['type']   = 'editor';
		require_once dirname(dirname(__FILE__)) . '/' . $editor . '/' . $editor . '.php';
		$classname		= 'plgEditor' . ucfirst($editor);
		JFactory::getLanguage()->load('plg_editors_' . $editor, JPATH_ADMINISTRATOR);
		$this->_switchereditor = new $classname($subject, $config);
	}

	/**
	 * Create the selector of editors
	 *
	 * @staticvar null $selector
	 * @param string $current
	 * @return string
	 */
	protected function _setEditorSelector($current)
	{
		static $selector = null;

		if (is_null($selector))
		{
			$db		 = JFactory::getDbo();
			$authGroups = JFactory::getUser()->getAuthorisedGroups();
			JArrayHelper::toInteger($authGroups);

			$query = $db->getQuery(true);
			$query->select($db->qn('element') . ' AS value');
			$query->select('CONCAT(UCASE(SUBSTRING(' . $db->qn('element')
					. ', 1, 1)), SUBSTRING(' . $db->qn('element') . ', 2)) AS text');
			$query->from($db->qn('#__extensions'));
			$query->where($db->qn('folder') . ' = ' . $db->q('editors'));
			$query->where($db->qn('type') . ' = ' . $db->q('plugin'));
			$query->where($db->qn('enabled') . ' = 1');
			$query->where($db->qn('element') . ' <> ' . $db->q('switcher'));
			$query->where($db->qn('access') . ' IN (' . implode(',', $authGroups) . ')');
			$query->order($db->qn('ordering'));
			$query->order($db->qn('name'));

			$db->setQuery($query);
			$editors = $db->loadObjectList();
			if (count($editors) < 2)
			{
				$selector = '';
				return $selector;
			}

			//Search Index of current editor
			$count = 0;
			$index = 0;
			$array = array();
			foreach($editors as $o)
			{
				$array[$o->value] = $count;

				if($o->value == $current){
					$index = $count;
				}

				$count++;
			}

			JHtml::_('behavior.framework');

			$params	   = JPluginHelper::getPlugin('editors', 'switcher')->params;
			$confirmation = $params->get('confirmation', 1);

			//onchange
			$js = 'onchange="';
			if ($confirmation)
			{
				$this->loadLanguage('plg_editors_switcher', JPATH_ADMINISTRATOR);
				$js .= 'if(this.options.selectedIndex != '. $index . ' && confirm(\''
						. JText::_('PLG_EDITORS_SWITCHER_CONFIRM_MESSAGE_TITLE', true)
						. '\r\n' . JText::_('PLG_EDITORS_SWITCHER_CONFIRM_MESSAGE', true) . '\')'
						. '){';
				$js .= 'jQuery(\'#editorswitcher-currentvalue\').value = this.options.selectedIndex;';
			}

			$domain = JURI::getInstance()->getHost();
			$days   = intval($params->get('cookie_days', 365));
			$js .= "Cookie.write('$this->cookiename', this.value, {duration:'$days', domain:'$domain'});";
			$js .= "Cookie.write('$this->cookiename', this.value, {duration:$days});window.location.reload();";

			if ($confirmation)
			{
				$js .= '} else {var curSelected = \'#jswitcheditor_chzn_o_\'+jswitcherEditors[document.id(\'editorswitcher-currentvalue\').value];
					if(jQuery(curSelected))jQuery(curSelected).trigger(\'mouseup\');}';
			}
			$js .= '"';

			//html0
			$selector = ''
			. '<div id="switcherSelector" style="vertical-align:middle;margin:0 0 0 5px;display:inline-block;"><input type="hidden" id="editorswitcher-currentvalue" value="'
			. $current . '" />'
			. JHtml::_('select.genericlist', $editors, 'switcheditor' . ''
					, 'class="btn" ' . $js, 'value', 'text', $current, 'jswitcheditor')
			. '</div>';

			// Write html and move and init list index
			$array = json_encode($array);
			$js = "var jswitcherEditors = $array;window.addEvent('domready', function(){var btnwrap = $$('#editor-xtd-buttons .btn-toolbar');if(btnwrap.length > 0)"
			. " document.id('switcherSelector').inject(btnwrap[0]);	setTimeout(function(){var curSelected = '#jswitcheditor_chzn_o_'+$index;if(jQuery(curSelected)) jQuery(curSelected).trigger('mouseup');}, 1000);});";
			JFactory::getDocument()->addScriptDeclaration($js);
		}

		return $selector;
	}

	/**
	 * Initialises the selected Editor.
	 *
	 * @return  string  JavaScript Initialization string
	 *
	 * @since 1.5
	 */
	public function onInit()
	{
		if (is_callable(array($this->_switchereditor, 'onInit')))
		{
			return $this->_switchereditor->onInit();
		}
	}

	/**
	 * Selected Editor - get the editor content
	 *
	 * @param  string  The name of the editor
	 *
	 * @return string
	 */
	public function onGetContent($editor)
	{
		if (is_callable(array($this->_switchereditor, 'onGetContent')))
		{
			return $this->_switchereditor->onGetContent($editor);
		}
	}

	/**
	 * Selected Editor - set the editor content
	 *
	 * @param   string  The name of the editor
	 *
	 * @return  string
	 */
	public function onSetContent($editor, $html)
	{
		if (is_callable(array($this->_switchereditor, 'onSetContent')))
		{
			return $this->_switchereditor->onSetContent($editor, $html);
		}
	}

	/**
	 * Selected Editor - copy editor content to form field
	 *
	 * @param   string  The name of the editor
	 *
	 * @return  string
	 */
	public function onSave($editor)
	{
		if (is_callable(array($this->_switchereditor, 'onSave')))
		{
			return $this->_switchereditor->onSave($editor);
		}
	}

	/**
	 * Display the editor area.
	 *
	 * @param   string   The name of the editor area.
	 * @param   string   The content of the field.
	 * @param   string   The width of the editor area.
	 * @param   string   The height of the editor area.
	 * @param   int      The number of columns for the editor area.
	 * @param   int      The number of rows for the editor area.
	 * @param   boolean  True and the editor buttons will be displayed.
	 * @param   string   An optional ID for the textarea. If not supplied the name is used.
	 *
	 * @return  string
	 */
	public function onDisplay($name, $content, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null)
	{
		if (is_callable(array($this->_switchereditor, 'onDisplay')))
		{
			return $this->_switchereditor->onDisplay($name, $content, $width, $height, $col, $row, $buttons, $id, $asset, $author) . $this->_setEditorSelector($this->_switchereditor->_name);
		}
	}

	public function onGetInsertMethod($name)
	{
		if (is_callable(array($this->_switchereditor, 'onGetInsertMethod')))
		{
			return $this->_switchereditor->onGetInsertMethod($name);
		}

		return true;
	}

}
