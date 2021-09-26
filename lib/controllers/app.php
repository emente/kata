<?php
/**
 * @package kata_controller
 */






/**
 * A dummy-class that is included if the user does not supply an appcontroller.

 * a common mistake:
 * if you implement beforeAction() in the appController AND in your own controller,
 * only the beforeAction() of your own controller will be called, because it's a decendant
 * of the appcontroller
 * @package kata_controller
 * @author mnt@codeninja.de
 */
class AppController extends Controller {
}

