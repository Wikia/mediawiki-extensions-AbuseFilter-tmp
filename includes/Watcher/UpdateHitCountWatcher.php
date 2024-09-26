<?php

namespace MediaWiki\Extension\AbuseFilter\Watcher;

use DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Logger\LoggerFactory;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Watcher that updates hit counts of filters
 */
class UpdateHitCountWatcher implements Watcher {
	public const SERVICE_NAME = 'AbuseFilterUpdateHitCountWatcher';

    private const LOGS_CHANNEL = 'AbuseFilterMonitoring';

    private const LIST = [101, 15, 16, 34, 11, 13, 37, 38, 39, 49, 124, 126, 130, 1, 2, 7, 9, 17, 22, 24, 26,
33, 36, 42, 43, 65, 69, 78, 74, 77, 87, 88, 95, 102, 109, 125, 129, 3, 18, 32, 55,
58, 62, 67, 93, 107, 116, 122, 31, 79, 89, 108, 117, 119, 121, 118, 27];


    /** @var ILoadBalancer */
	private $loadBalancer;

	/** @var CentralDBManager */
	private $centralDBManager;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param CentralDBManager $centralDBManager
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		CentralDBManager $centralDBManager
	) {
		$this->loadBalancer = $loadBalancer;
		$this->centralDBManager = $centralDBManager;
	}

	/**
	 * @inheritDoc
	 */
	public function run( array $localFilters, array $globalFilters, string $group ): void {
		// Run in a DeferredUpdate to avoid primary database queries on raw/view requests (T274455)
		DeferredUpdates::addCallableUpdate( function () use ( $localFilters, $globalFilters ) {
			if ( $localFilters ) {
				$this->updateHitCounts( $this->loadBalancer->getConnectionRef( DB_PRIMARY ), $localFilters );
			}

			if ( $globalFilters ) {
				$fdb = $this->centralDBManager->getConnection( DB_PRIMARY );
				$this->updateHitCounts( $fdb, $globalFilters );
			}
		} );
	}

	/**
	 * @param IDatabase $dbw
	 * @param array $loggedFilters
	 */
	private function updateHitCounts( IDatabase $dbw, array $loggedFilters ): void {
        foreach ($loggedFilters as $id) {
            if (in_array($id, self::LIST)) {
                LoggerFactory::getInstance(self::LOGS_CHANNEL)->info("Abuse filter - hits update data base - " . implode(', ', $loggedFilters));
            }
        }
        $dbw->update(
			'abuse_filter',
			[ 'af_hit_count=af_hit_count+1' ],
			[ 'af_id' => $loggedFilters ],
			__METHOD__
		);
	}
}
