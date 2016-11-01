<?php
class FilesZipFolderModule extends AApiModule
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
		$this->subscribeEvent('Files::GetFiles::before', array($this, 'onBeforeGetFiles'), 50);
		$this->subscribeEvent('Files::GetFiles::after', array($this, 'onAfterGetFiles'), 50);
		$this->subscribeEvent('Files::CreateFolder::before', array($this, 'onBeforeCreateFolder'), 50);
		$this->subscribeEvent('Files::CreateFile', array($this, 'onCreateFile'), 50);
		$this->subscribeEvent('Files::Delete::before', array($this, 'onBeforeDelete'), 50);
		$this->subscribeEvent('Files::Rename::before', array($this, 'onBeforeRename'), 50);
		$this->subscribeEvent('Files::Move::before', array($this, 'onBeforeMove'), 50);
		$this->subscribeEvent('Files::Copy::before', array($this, 'onBeforeCopy'), 50); 
		$this->subscribeEvent('Files::GetFileInfo::after', array($this, 'onAfterGetFileInfo'));
	}
	
	/**
	 * Returns directory name for the specified path.
	 * 
	 * @param string $sPath Path to the file.
	 * @return string
	 */
	protected function getDirName($sPath)
	{
		$sPath = dirname($sPath);
		return str_replace(DIRECTORY_SEPARATOR, '/', $sPath); 
	}
	
	/**
	 * Returns base name for the specified path.
	 * 
	 * @param string $sPath Path to the file.
	 * @return string
	 */
	protected function getBaseName($sPath)
	{
		$aPath = explode('/', $sPath);
		return end($aPath); 
	}

	/**
	 * Populates file info.
	 * 
	 * @param string $sType Service type.
	 * @param \Dropbox\Client $oClient DropBox client.
	 * @param array $aData Array contains information about file.
	 * @return \CFileStorageItem|false
	 */
	protected function populateFileInfo($sType, $oClient, $aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mResult = false;
		if ($aData && is_array($aData))
		{
			$sPath = ltrim($this->getDirName($aData['path']), '/');
			
//			$oSocial = $this->GetSocial($oAccount);
			$mResult /*@var $mResult \CFileStorageItem */ = new  \CFileStorageItem();
//			$mResult->IsExternal = true;
			$mResult->TypeStr = $sType;
			$mResult->IsFolder = $aData['is_dir'];
			$mResult->Id = $this->getBaseName($aData['path']);
			$mResult->Name = $mResult->Id;
			$mResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$mResult->Size = $aData['bytes'];
//			$bResult->Owner = $oSocial->Name;
			$mResult->LastModified = date_timestamp_get($oClient->parseDateTime($aData['modified']));
			$mResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$mResult->FullPath = $mResult->Name !== '' ? $mResult->Path . '/' . $mResult->Name : $mResult->Path ;

			if (!$mResult->IsFolder && $aData['thumb_exists'])
			{
				$mResult->Thumb = true;
			}
			
		}
		return $mResult;
	}	
	
	/**
	 * Writes to the $mResult variable open file source if $sType is DropBox account type.
	 * 
	 * @ignore
	 * @param int $iUserId Identificator of the authenticated user.
	 * @param string $sType Service type.
	 * @param string $sPath File path.
	 * @param string $sName File name.
	 * @param boolean $bThumb **true** if thumbnail is expected.
	 * @param mixed $mResult
	 */
	public function onGetFile($aArgs, &$mResult)
	{
		$sPath = $aArgs['Path'];
		$aPathInfo = pathinfo($sPath);
		if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
		{
			$sFileName = $aArgs['Name'];
			$aArgs['Name'] = basename($sPath);
			$aArgs['Path'] = dirname($sPath);
			$oFileInfo = false;
			\CApi::GetModuleManager()->broadcastEvent(
				'Files', 
				'GetFileInfo::after', 
				$aArgs, 
				$oFileInfo
			);
			if ($oFileInfo)
			{
				$za = new ZipArchive(); 
				$za->open($oFileInfo->RealPath); 
				$mResult = $za->getStream($sFileName);
				if (is_resource($mResult))
				{
					$aArgs['Name'] = basename($sFileName);
					return true;
				}
			}
		}
	}	

	public function onBeforeGetFiles($aArgs, &$mResult)
	{
		if (isset($aArgs['Path']))
		{
			$sPath = $aArgs['Path'];
			$sIndex = '';
			if (strpos($sPath, '$ZIP:'))
			{
				list($sPath, $sIndex) = explode('$ZIP:', $sPath);
			}
			$aPathInfo = pathinfo($sPath);
			if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
			{
				$aArgs['Name'] = basename($sPath);
				$aArgs['Path'] = dirname($sPath);
				$oFileInfo = false;
				\CApi::GetModuleManager()->broadcastEvent(
					'Files', 
					'GetFileInfo::after', 
					$aArgs, 
					$oFileInfo
				);
				if ($oFileInfo)
				{
					$za = new ZipArchive(); 
					$za->open($oFileInfo->RealPath); 

					$mResult = array();
					for( $i = 0; $i < $za->numFiles; $i++ )
					{ 
						$aStat = $za->statIndex($i); 
						$sStatName = $aStat['name'];
						if (!empty($sStatName) && !empty($sIndex)) 
						{
							if(strpos($sStatName, $sIndex) === 0)
							{
								$sStatName = substr($sStatName, strlen($sIndex));
							}
							else
							{
								$sStatName = '';
							}
						}
						if (!empty($sStatName))
						{
							$oItem /*@var $oItem \CFileStorageItem */ = new  \CFileStorageItem();
							$oItem->Id = $aStat['name'];
							$oItem->Path = $sPath;
							$oItem->FullPath = $oItem->Path . '$ZIP:' . $oItem->Id;
							if ($aStat['size'] === 0)
							{
								$oItem->IsFolder = true;
							}
							else
							{
								$oItem->Size = $aStat['size'];
							}

							$aPath = explode('/', $sStatName);
							$sName = $aPath[0];

							if (!isset($mResult['Items'][$sName]))
							{
								$oItem->Name = $sName;
								$mResult['Items'][$sName] = $oItem;
							}
						}
					}
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
	public function onAfterGetFiles($aArgs, &$mResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		if (isset($mResult['Items']) && is_array($mResult['Items']))
		{
			foreach($mResult['Items'] as $oItem)
			{
				$aPathInfo = pathinfo($oItem->Name);
				if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
				{
					$oItem->IsFolder = true;
					$oItem->Path = 'zip:';
				}
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
	public function onBeforeDelete($aArgs, &$mResult)
	{
	}	

	/**
	 * Renames file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onBeforeRename($aArgs, &$mResult)
	{
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
		$aPathInfo = pathinfo($sPath);
		if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
		{
			$sFileName = $aArgs['Name'];
			$aArgs['Name'] = basename($sPath);
			$aArgs['Path'] = dirname($sPath);
			$oFileInfo = false;
			\CApi::GetModuleManager()->broadcastEvent(
				'Files', 
				'GetFileInfo::after', 
				$aArgs, 
				$oFileInfo
			);
			if ($oFileInfo)
			{
				$za = new ZipArchive(); 
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
		return true;
	}		
	
	/**
	 * @ignore
	 * @todo not used
	 * @param object $oAccount
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 * @param boolean $bResult
	 * @param boolean $bBreak
	 */
	public function onAfterGetFileInfo($aArgs, &$bResult)
	{
	}	
	
	/**
	 * @ignore
	 * @todo not used
	 * @param object $oItem
	 * @return boolean
	 */
	public function onPopulateFileItem($oItem, &$mResult)
	{
	}	
	/***** private functions *****/
}
