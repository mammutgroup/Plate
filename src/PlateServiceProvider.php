<?php
	namespace Plate;

	use Illuminate\Support\ServiceProvider;

	class PlateServiceProvider extends ServiceProvider
	{

		/**
	     * Bootstrap any application services.
	     *
	     * @return void
	     */
		public function boot()
		{
			$this->publishes([
            	__DIR__ . '/config.php' => config_path('plate.php')
         	], 'config');

         	\App::bind('plate', function()
			{
			    return new Plate;
			});
		}

		/**
		 * Register any application services.
		 *
		 * @return void
	     */
	    public function register()
	    {

	    }
	}