<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\FilesZipFolder;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	/***** private functions *****/
	/**
	 * Initializes FilesZipFolder Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'), 50);
//		$this->subscribeEvent('Files::GetItems::before', array($this, 'onBeforeGetItems'), 500);
		$this->subscribeEvent('Files::GetItems', array($this, 'onGetItems'), 50);
		$this->subscribeEvent('Files::CreateFolder::before', array($this, 'onBeforeCreateFolder'), 50);
		$this->subscribeEvent('Files::CreateFile', array($this, 'onCreateFile'), 50);
		$this->subscribeEvent('Files::Delete::after', array($this, 'onAfterDelete'), 50);
		$this->subscribeEvent('Files::Rename::after', array($this, 'onAfterRename'), 50);
		$this->subscribeEvent('Files::Move::before', array($this, 'onBeforeMove'), 50);
		$this->subscribeEvent('Files::Copy::before', array($this, 'onBeforeCopy'), 50); 
		$this->subscribeEvent('Files::GetFileInfo::after', array($this, 'onAfterGetFileInfo'), 500);
		$this->subscribeEvent('Files::PopulateFileItem::after', array($this, 'onAfterPopulateFileItem'));
		
	}
	
	/**
	 * Returns directory name for the specified path.
	 * 
	 * @param string $sPath Path to the file.
	 * @return string
	 */
	protected function getDirName($sPath)
	{
		$sPath = \dirname($sPath);
		return \str_replace(DIRECTORY_SEPARATOR, '/', $sPath); 
	}
	
	/**
	 * Returns base name for the specified path.
	 * 
	 * @param string $sPath Path to the file.
	 * @return string
	 */
	protected function getBaseName($sPath)
	{
		$aPath = \explode('/', $sPath);
		return \end($aPath); 
	}

	/**
	 * Populates file info.
	 * 
	 * @param string $sType Service type.
	 * @param \Dropbox\Client $oClient DropBox client.
	 * @param array $aData Array contains information about file.
	 * @return \Aurora\Modules\Files\Classes\FileItem|false
	 */
	protected function populateFileInfo($sType, $oClient, $aData)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$mResult = false;
		if ($aData && \is_array($aData))
		{
			$sPath = \ltrim($this->getDirName($aData['path']), '/');
			
//			$oSocial = $this->GetSocial($oAccount);
			$mResult /*@var $mResult \Aurora\Modules\Files\Classes\FileItem */ = new  \Aurora\Modules\Files\Classes\FileItem();
//			$mResult->IsExternal = true;
			$mResult->TypeStr = $sType;
			$mResult->IsFolder = $aData['is_dir'];
			$mResult->Id = $this->getBaseName($aData['path']);
			$mResult->Name = $mResult->Id;
			$mResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$mResult->Size = $aData['bytes'];
//			$bResult->Owner = $oSocial->Name;
			$mResult->LastModified = \date_timestamp_get($oClient->parseDateTime($aData['modified']));
			$mResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$mResult->FullPath = $mResult->Name !== '' ? $mResult->Path . '/' . $mResult->Name : $mResult->Path ;

			if (!$mResult->IsFolder && $aData['thumb_exists'])
			{
				$mResult->Thumb = true;
			}
			
		}
		return $mResult;
	}	
	
	public function getItemHash($oItem)
	{
		return \Aurora\System\Api::EncodeKeyValues(array(
			'UserId' => \Aurora\System\Api::getAuthenticatedUserId(), 
			'Type' => $oItem->TypeStr,
			'Path' => $oItem->FullPath,
			'Name' => $oItem->Name
		));			
	}
	
	/**
	 * Writes to the $mResult variable open file source if $sType is DropBox account type.
	 * 
	 * @ignore
	 * @param int $iUserId Identifier of the authenticated user.
	 * @param string $sType Service type.
	 * @param string $sPath File path.
	 * @param string $sName File name.
	 * @param boolean $bThumb **true** if thumbnail is expected.
	 * @param mixed $mResult
	 */
	public function onGetFile($aArgs, &$mResult)
	{
		$sPath = $aArgs['Path'];
		if (\strpos($sPath, '$ZIP:'))
		{
			list($sPath, $sIndex) = \explode('$ZIP:', $sPath);
		}
		$aPathInfo = \pathinfo($sPath);
		if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
		{
			$aArgs['Id'] = \basename($sPath);
			$aArgs['Path'] = \dirname($sPath) === '\\' ? '' : \dirname($sPath);
			$oFileInfo = false;
			\Aurora\System\Api::GetModuleManager()->broadcastEvent(
				'Files', 
				'GetFileInfo::after', 
				$aArgs, 
				$oFileInfo
			);

			if ($oFileInfo && class_exists('ZipArchive'))
			{
				$za = new \ZipArchive(); 
				$za->open($oFileInfo->RealPath); 
				$mResult = $za->getStream($sIndex);
				if (\is_resource($mResult))
				{
					$aArgs['Name'] = \basename($sIndex);
					return true;
				}
			}
		}
	}	

	/**
	 * Writes to $aData variable list of DropBox files if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onGetItems($aArgs, &$mResult)
	{
		
		if (isset($aArgs['Path']))
		{
			$sPath = $aArgs['Path'];
			$sIndex = '';
			if (\strpos($sPath, '$ZIP:'))
			{
				list($sPath, $sIndex) = \explode('$ZIP:', $sPath);
			}
			$aPathInfo = \pathinfo($sPath);
			if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
			{
				$aGetFileInfoArgs = array(
					'Id' => \basename($sPath),
					'Name' => \basename($sPath),
					'Path' => \trim(\dirname($sPath), '\\'),
					'UserId' => $aArgs['UserId'],
					'Type' => $aArgs['Type']
				);
				$oFileInfo = false;
				\Aurora\System\Api::GetModuleManager()->broadcastEvent(
					'Files', 
					'GetFileInfo::after', 
					$aGetFileInfoArgs, 
					$oFileInfo
				);

				if ($oFileInfo && class_exists('ZipArchive'))
				{
					$za = new \ZipArchive(); 
					$za->open($oFileInfo->RealPath); 

					$mResult = array();
					$aItems = array();
					for( $i = 0; $i < $za->numFiles; $i++ )
					{ 
						$aStat = $za->statIndex($i); 
						$sStatName = $aStat['name'];
						if (!empty($sStatName) && !empty($sIndex)) 
						{
							if(strpos($sStatName, $sIndex) === 0)
							{
								$sStatName = \substr($sStatName, \strlen($sIndex));
							}
							else
							{
								$sStatName = '';
							}
						}
						if (!empty($sStatName))
						{
							$oItem /*@var $oItem \Aurora\Modules\Files\Classes\FileItem */ = new  \Aurora\Modules\Files\Classes\FileItem();
							$oItem->Id = $aStat['name'];
							$oItem->Path = $sPath;
							$oItem->TypeStr = $aArgs['Type'];
							$oItem->FullPath = $oItem->Path . '$ZIP:' . $oItem->Id;
							if ($aStat['size'] === 0)
							{
								$oItem->IsFolder = true;
							}
							else
							{
								$oItem->Size = $aStat['size'];
							}
							$oItem->ContentType = \MailSo\Base\Utils::MimeContentType($oItem->Id);

							$aPath = \explode('/', $sStatName);
							$sName = $aPath[0];

							if (!isset($aItems[$sName]))
							{
								$oItem->Name = $sName;
								$aItems[$sName] = $oItem;
							}
							
							if ($oItem->IsFolder)
							{
								$oItem->AddAction([
									'list' => []
								]);
							}
							else
							{
								$oItem->AddAction([
									'view' => [
										'url' => '?download-file/' . $this->getItemHash($oItem) .'/view'
									]
								]);
								$oItem->AddAction([
									'download' => [
										'url' => '?download-file/' . $this->getItemHash($oItem)
									]
								]);
								
								$sMimeType = \MailSo\Base\Utils::MimeContentType($sName);
								$oSettings =& \Aurora\System\Api::GetSettings();
								$iThumbnailLimit = ((int) $oSettings->GetValue('ThumbnailMaxFileSizeMb', 5)) * 1024 * 1024;
								if ($oSettings->GetValue('AllowThumbnail', true) &&
										$oItem->Size < $iThumbnailLimit && \Aurora\System\Utils::IsGDImageMimeTypeSuppoted($sMimeType, $sName))
								{
									$oItem->Thumb = true;
									$oItem->ThumbnailUrl = '?download-file/' . $this->getItemHash($oItem) .'/thumb';
								}
							}
						}
					}
					$mResult = \array_values($aItems);
				}
				return true;
			}
		}		
	}	

	/**
	 * Creates folder if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onBeforeCreateFolder($aArgs, &$mResult)
	{
	}	

	/**
	 * Creates file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onCreateFile($aArgs, &$Result)
	{
	}	

	/**
	 * Deletes file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterDelete($aArgs, &$mResult)
	{
		$bResult = false;
		
		foreach ($aArgs['Items'] as $aItem)
		{
			$sPath = $aItem['Path'];
			$aPathInfo = \pathinfo($sPath);
			if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
			{
				$sName = $aItem['Name'];
				$aGetFileInfoArgs = $aArgs;
				$aGetFileInfoArgs['Name'] = \basename($sPath);
				$aGetFileInfoArgs['Path'] = \dirname($sPath);
				$oFileInfo = false;
				\Aurora\System\Api::GetModuleManager()->broadcastEvent(
					'Files', 
					'GetFileInfo::after', 
					$aGetFileInfoArgs, 
					$oFileInfo
				);
				if ($oFileInfo && class_exists('ZipArchive'))
				{
					$za = new \ZipArchive(); 
					$za->open($oFileInfo->RealPath);
					$mResult = $za->deleteName($sName);
					$bResult = $mResult;
				}
			}
		}
		return $bResult;
	}	

	/**
	 * Renames file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterRename($aArgs, &$mResult)
	{
		$sPath = $aArgs['Path'];
		$aPathInfo = \pathinfo($sPath);
		if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
		{
			$sName = $aArgs['Name'];
			$sNewName = $aArgs['NewName'];
			$aArgs['Name'] = \basename($sPath);
			$aArgs['Path'] = \dirname($sPath);
			$oFileInfo = false;
			\Aurora\System\Api::GetModuleManager()->broadcastEvent(
				'Files', 
				'GetFileInfo::after', 
				$aArgs, 
				$oFileInfo
			);
			if ($oFileInfo && class_exists('ZipArchive'))
			{
				$za = new \ZipArchive(); 
				$za->open($oFileInfo->RealPath);
				$sFileDir = \dirname($sName);
				if ($sFileDir !== '.')
				{
					$sNewFullPath = $sFileDir . $sNewName;
				}
				else 
				{
					$sNewFullPath = $sNewName;
				}
				$mResult = $za->renameName($sName, $sNewFullPath);
				$za->close();
			}
			return $mResult;
		}
	}	

	/**
	 * Moves file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onBeforeMove($aArgs, &$mResult)
	{
		$sPath = $aArgs['FromPath'];
		$aPathInfo = \pathinfo($sPath);
		if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
		{
			$sFileName = $aArgs['Name'];
			$aArgs['Name'] = \basename($sPath);
			$aArgs['Path'] = \dirname($sPath);
			$oFileInfo = false;
			\Aurora\System\Api::GetModuleManager()->broadcastEvent(
				'Files', 
				'GetFileInfo::after', 
				$aArgs, 
				$oFileInfo
			);
			if ($oFileInfo && class_exists('ZipArchive'))
			{
				$za = new \ZipArchive(); 
				$za->open($oFileInfo->RealPath); 
			}
		}
	}	

	/**
	 * Copies file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onBeforeCopy($aArgs, &$mResult)
	{
	}		
	
	/**
	 * @ignore
	 * @todo not used
	 * @param object $oAccount
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 * @param mixed $mResult
	 * @param boolean $bBreak
	 */
	public function onAfterGetFileInfo($aArgs, &$mResult)
	{
	}	
	
	/**
	 * @ignore
	 * @todo not used
	 * @param object $oItem
	 * @return boolean
	 */
	public function onAfterPopulateFileItem($oItem, &$mResult)
	{
		if (isset($mResult))
		{
			$aPathInfo = \pathinfo($mResult->Name);
			if (class_exists('ZipArchive') && isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
			{
				$mResult->UnshiftAction(array(
					'list' => array()
				));
			}
		}
	}	
	/***** private functions *****/
}
