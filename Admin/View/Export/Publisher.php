<?php

declare(strict_types=1);

namespace cv6\RPG\Admin\View\Export;

use XF\Mvc\View;

class Publisher extends View
{
	public function renderRaw()
	{
		$this->response->setDownloadFileName($this->params['fileName']);

		if (!empty($this->params['isZip']))
		{
			$this->response->contentType('application/zip');
			return $this->params['zipData'];
		}

		$this->response->contentType('application/json', 'utf-8');
		return $this->params['json'];
	}
}
