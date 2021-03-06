<?php
/**
 * @package ABTag
 * @copyright Copyright (c) 2017 - 2017 Lucas Sanner
 * @license GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/**
 * ABTag Component Route Helper
 *
 * @static
 * @package     Joomla.Site
 * @subpackage  com_abtag
 * @since       1.5
 */
abstract class AbtagHelperRoute
{
  protected static $lookup;

  protected static $lang_lookup = array();

  /**
   * @param   integer  The route of the entry
   */
  public static function getEntryRoute($id, $tagIds, $language = 0)
  {
    $needles = array('entry' => array((int) $id));

    //Create the link
    $link = 'index.php?option=com_abtag&view=entry&id='.$id;

    if(!empty($tagIds)) {
      $needles['tag'] = $tagIds;
      $link .= '&tagid='.$tagIds[0];
    }


    if($language && $language != "*" && JLanguageMultilang::isEnabled()) {
      self::buildLanguageLookup();

      if(isset(self::$lang_lookup[$language])) {
	$link .= '&lang=' . self::$lang_lookup[$language];
	$needles['language'] = $language;
      }
    }

    if($item = self::_findItem($needles)) {
      $link .= '&Itemid='.$item;
    }

    return $link;
  }


  public static function getCategoryRoute($catid, $language = 0)
  {
    if($catid instanceof JCategoryNode) {
      $id = $catid->id;
      $category = $catid;
    }
    else {
      $id = (int) $catid;
      $category = JCategories::getInstance('Abtag')->get($id);
    }

    if($id < 1 || !($category instanceof JCategoryNode)) {
      $link = '';
    }
    else {
      $needles = array();

      // Create the link
      $link = 'index.php?option=com_abtag&view=category&id='.$id;

      $catids = array_reverse($category->getPath());
      $needles['category'] = $catids;
      $needles['categories'] = $catids;

      if($language && $language != "*" && JLanguageMultilang::isEnabled()) {
	self::buildLanguageLookup();

	if(isset(self::$lang_lookup[$language])) {
	  $link .= '&lang=' . self::$lang_lookup[$language];
	  $needles['language'] = $language;
	}
      }

      if ($item = self::_findItem($needles)) {
	$link .= '&Itemid='.$item;
      }
    }

    return $link;
  }


  public static function getTagRoute($id, $path, $language = 0)
  {
    if((int)$id < 1) {
      $link = '';
    }
    else {
      $link = 'index.php?option=com_abtag&view=tag&id='.$id;
      //Converts the tag path into an item array.
      $items = explode('/', $path);
      $ids = array();

      if(count($items) > 1) {
	$paths = $slash = $in = '';
	$db = JFactory::getDbo();
	$query = $db->getQuery(true);

	//Build recursively the path values for the IN MySQL clause (eg: 'item1','item1/item2', etc...).
	foreach($items as $item) {
	  $paths .= $slash.$item;
	  $in .= $db->quote($paths).',';
	  $slash = '/';
	}

	//Remove comma from the end of the string.
	$in = substr($in, 0, -1);

	//Gets the parent item ids.
	$query->select('id')
	      ->from('#__tags')
	      ->where('path IN('.$in.') AND published=1');

	if($language && $language != "*" && JLanguageMultilang::isEnabled()) {
	  $query->where('language='.$db->quote($language));
	}

	      $query->order('level DESC');
	$db->setQuery($query);
	$ids = $db->loadColumn();
      }
      else {
	//The tag is a first level item therefore it has no parent.
	$ids[] = (int)$id;
      }

      $needles = array('tag'  => $ids);

      if($language && $language != "*" && JLanguageMultilang::isEnabled()) {
	self::buildLanguageLookup();

	if(isset(self::$lang_lookup[$language])) {
	  $link .= '&lang=' . self::$lang_lookup[$language];
	  $needles['language'] = $language;
	}
      }

      if($item = self::_findItem($needles)) {
	$link .= '&Itemid='.$item;
      }
    }

    return $link;
  }


  protected static function buildLanguageLookup()
  {
    if(count(self::$lang_lookup) == 0) {
      $db = JFactory::getDbo();
      $query = $db->getQuery(true)
	      ->select('l.sef AS sef')
	      ->select('l.lang_code AS lang_code')
	      ->from('#__languages AS l');

      $db->setQuery($query);
      $langs = $db->loadObjectList();

      foreach($langs as $lang) {
	self::$lang_lookup[$lang->lang_code] = $lang->sef;
      }
    }
  }


  protected static function _findItem($needles = null)
  {
    $app = JFactory::getApplication();
    $menus = $app->getMenu('site');
    $language = isset($needles['language']) ? $needles['language'] : '*';

    // Prepare the reverse lookup array.
    if(!isset(self::$lookup[$language])) {
      self::$lookup[$language] = array();

      $component = JComponentHelper::getComponent('com_abtag');

      $attributes = array('component_id');
      $values = array($component->id);

      if($language != '*') {
	$attributes[] = 'language';
	$values[] = array($needles['language'], '*');
      }

      $items = $menus->getItems($attributes, $values);

      if($items) {
	foreach($items as $item) {
	  if(isset($item->query) && isset($item->query['view'])) {
	    $view = $item->query['view'];
	    if(!isset(self::$lookup[$language][$view])) {
	      self::$lookup[$language][$view] = array();
	    }
	    if(isset($item->query['id'])) {
	      // here it will become a bit tricky
	      // language != * can override existing entries
	      // language == * cannot override existing entries
	      if(!isset(self::$lookup[$language][$view][$item->query['id']]) || $item->language != '*') {
		self::$lookup[$language][$view][$item->query['id']] = $item->id;
	      }
	    }
	  }
	}
      }
    }

    if($needles) {
      foreach($needles as $view => $ids) {
	if(isset(self::$lookup[$language][$view])) {
	  foreach($ids as $id) {
	    if(isset(self::$lookup[$language][$view][(int) $id])) {
	      return self::$lookup[$language][$view][(int) $id];
	    }
	  }
	}
      }
    }

    // Check if the active menuitem matches the requested language
    $active = $menus->getActive();
    if($active && ($language == '*' || in_array($active->language, array('*', $language)) || !JLanguageMultilang::isEnabled())) {
      return $active->id;
    }

    // If not found, return language specific home link
    $default = $menus->getDefault($language);
    return !empty($default->id) ? $default->id : null;
  }
}
