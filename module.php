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
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'));
		$this->subscribeEvent('Files::GetFiles::before', array($this, 'onBeforeGetFiles'));
		$this->subscribeEvent('Files::GetFiles::after', array($this, 'onAfterGetFiles'));
		$this->subscribeEvent('Files::CreateFolder::after', array($this, 'onAfterCreateFolder'));
		$this->subscribeEvent('Files::CreateFile', array($this, 'onCreateFile'));
		$this->subscribeEvent('Files::Delete::after', array($this, 'onAfterDelete'));
		$this->subscribeEvent('Files::Rename::after', array($this, 'onAfterRename'));
		$this->subscribeEvent('Files::Move::after', array($this, 'onAfterMove'));
		$this->subscribeEvent('Files::Copy::after', array($this, 'onAfterCopy')); 
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
	}	

	public function onBeforeGetFiles($aArgs, &$mResult)
	{
		if (isset($aArgs['Path']))
		{
			$aPathInfo = pathinfo($aArgs['Path']);
			if (isset($aPathInfo['extension']) && $aPathInfo['extension'] === 'zip')
			{
				$aArgs['Name'] = basename($aArgs['Path']);
				$aArgs['Path'] = dirname($aArgs['Path']);
				$oFileInfo = false;
				\CApi::GetModuleManager()->broadcastEvent('Files', 'GetFileInfo::after', $aArgs, $oFileInfo);
				if ($oFileInfo)
				{
					$za = new ZipArchive(); 
					$za->open($oFileInfo->RealPath); 

					for( $i = 0; $i < $za->numFiles; $i++ )
					{ 
						$stat = $za->statIndex( $i ); 
						var_dump($stat); 
					}
				}
				exit;
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
	public function onAfterCreateFolder($aArgs, &$mResult)
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
	}	

	/**
	 * Renames file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterRename($aArgs, &$mResult)
	{
	}	

	/**
	 * Moves file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterMove($aArgs, &$mResult)
	{
	}	

	/**
	 * Copies file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterCopy($aArgs, &$mResult)
	{
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
