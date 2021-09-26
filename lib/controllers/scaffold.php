<?php
/**
 * @package kata_scaffold
 */







/**
 * default scaffolding-controller. extend it and use the hooks to costumize functionality
 * @package kata_scaffold
 * @author mnt@codeninja.de
 */
class ScaffoldController extends AppController {

	// hooks, overwrite them if you want

	function beforeDelete($id = null) {
		return true;
	}

	function beforeUpdate($id = null) {
		return true;
	}

	function beforeInsert($data = null) {
		return true;
	}

	// the scaffolder

	function beforeAction() {
		parent::beforeAction();
	}
	function beforeFilter() {
		parent::beforeFilter();
	}
	function afterFilter() {
		parent::afterFilter();
	}

	public $rowsPerPage = 50;

	private $MyModel = null;
	private function findModelToScaffold() {
		if (!is_array($this->uses) || (count($this->uses) == 0)) {
			throw new Exception('No Model to scaffold found');
		}

		$name = $this->uses[count($this->uses) - 1];
		$this->MyModel = getModel($name);
	}

	private function generateId() {
		
	}

	final function index() {
		$page = (int)is($this->params['url']['page'],0);
		$this->layout = '../../lib/views/layouts/scaffold';
		$this->findModelToScaffold();
		$this->set('schema', $this->MyModel->describe());
		$pages = ceil($this->MyModel->find('count')/$this->rowsPerPage);
		$this->set('pages', $pages);
		$this->set('page', min($pages,max(0,$page)));
		$this->set('data', $this->MyModel->find('all',array(
			'limit'=>$this->rowsPerPage,
			'page'=>$page
		)));
		$this->render('../../lib/views/scaffold/index');
	}

	final function delete($id = null) {
		if (!$this->beforeDelete($id)) {
			return;
		}
		$page = (int)is($this->params['url']['page'],0);

		if (isset ($id) && is_numeric($id)) {
			$this->findModelToScaffold();
			$this->MyModel->delete($id);
		}
		$this->redirect($this->params['controller'] . '/index/?page=' . $page);
	}

	final function update($id = null) {
		$this->layout = '../../lib/views/layouts/scaffold';

		if (isset ($this->params['form']['id']) && is_numeric($this->params['form']['id'])) {
			if ($this->beforeUpdate($id)) {
				$this->findModelToScaffold();
				$schema = $this->MyModel->describe();
				$data = $this->params['form']['data'];
				$id = $data[$schema['primary']];
				unset ($data[$schema['primary']]);
				if ($this->MyModel->update($id, $data)) {
					$this->redirect('/' . $this->params['controller'] . '/index');
					return;
				}
			}
		}

		if (isset ($id) && is_numeric($id)) {
			$this->findModelToScaffold();
			$this->set('update', $id);
			$this->set('schema', $this->MyModel->describe());
			$data = $this->MyModel->read($id);
			$this->set('data', array_shift($data));
			$this->set('formData', isset ($this->params['form']['data']) ? $this->params['form']['data'] : array ());
			$this->render('../../lib/views/scaffold/record');
			return;
		}

		$this->redirect($this->params['controller'] . '/index/');
	}

	final function insert() {
		$this->layout = 'scaffold';
		$this->set('update', 0);

		if (isset ($this->params['form']['data']) && is_array($this->params['form']['data'])) {
			if ($this->beforeInsert($this->params['form']['data'])) {
				$this->findModelToScaffold();
				$schema = $this->MyModel->describe();
				$data = $this->params['form']['data'];
				$id = $data[$schema['primary']];
				unset ($data[$schema['primary']]);
				if ($this->MyModel->create($data)) {
					$this->redirect('/' . $this->params['controller'] . '/index');
					return;
				}
			}
		}

		$this->findModelToScaffold();
		$this->set('schema', $this->MyModel->describe());
		$this->set('data', array ());
		$this->set('formData', isset ($this->params['form']['data']) ? $this->params['form']['data'] : array ());
		$this->render('../../lib/views/scaffold/record');
	}

}
