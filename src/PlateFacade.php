<?php
	namespace Plate;
	
	use Illuminate\Support\Facades\Facade;

	class PlateFacade extends Facade
	{
	    /**
	     * Get the registered name of the component.
	     *
	     * @return string
	     */
	    protected static function getFacadeAccessor() { return 'plate'; }
	}