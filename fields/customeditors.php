<?php
/**
 * @version		$Id: editors.php 10707 2008-08-21 09:52:47Z eddieajau $
 * @package		Joomla.Framework
 * @subpackage	Parameter
 * @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
// Check to ensure this file is within the rest of the framework
defined('JPATH_PLATFORM') or die();

/**
 * Renders a editors element
 *
 * @package 	Joomla.Framework
 * @subpackage		Parameter
 * @since		1.5
 */
class JFormFieldCustomEditors extends JFormField
{

	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	protected $type = 'customeditors';

	protected function getInput()
	{
		$db = JFactory::getDbo();

		$authGroups = JFactory::getUser()->getAuthorisedGroups();
		JArrayHelper::toInteger($authGroups);

		$query = $db->getQuery(true);
		$query->select($db->qn('element') . ' AS value');
		$query->select($db->qn('element') . ' AS text');
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

		if ($this->element['addall'])
		{
			array_unshift($editors
					, JHtml::_('select.option', '0', JText::_('PLG_EDITORS_SWITCHER_ALLEDITOR')));
		}

		if ($this->element['allownoselect'])
		{
			array_unshift($editors
					, JHtml::_('select.option', '-1', '- ' . JText::_('PLG_EDITORS_SWITCHER_SELECTEDITOR') . ' -'));
		}

		$name = $this->name;
		$attr = 'class="inputbox"';
		if ($this->element['multiple'])
		{
			$name .= '[]';
			$attr .= ' multiple="multiple" size="' . count($editors) . '"';
		}

		return JHtml::_('select.genericlist', $editors, $name, array(
					'id'		  => $this->id,
					'list.attr'   => $attr,
					'list.select' => $this->value
						)
		);
	}

}
