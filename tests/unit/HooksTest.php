<?php

namespace MessageCachePerformance;

use Config;
use LocalisationCache;
use PHPUnit\Framework\TestCase;

class HooksTest extends TestCase {
	public function testShouldReturnTrueOnKnownMessages() {
		$key = 'known_key';
		$localisationCache = $this->createMock( LocalisationCache::class );
		$matcher = $this->createMock( MessageCachePerformanceMatcher::class );

		$matcher->expects( $this->never() )
			->method( 'searchIn' );
		$localisationCache->expects( $this->once() )
			->method( 'getSubitemList' )
			->willReturn( [ $key ] );

		$hooks = new Hooks(
			$this->createMock( Config::class ),
			$localisationCache,
			$matcher
		);
		$result = $hooks->onMessageCache__get( $key );
		$this->assertTrue( $result );
	}

	public function testShouldReturnTrueUnknownNotMatchedKey() {
		$key = 'unknown_key';
		$localisationCache = $this->createMock( LocalisationCache::class );
		$matcher = $this->createMock( MessageCachePerformanceMatcher::class );

		$matcher->expects( $this->once() )
			->method( 'searchIn' )
			->with( $key )
			->willReturn( false );
		$localisationCache->expects( $this->once() )
			->method( 'getSubitemList' )
			->willReturn( [ 'known_key' ] );

		$hooks = new Hooks(
			$this->createMock( Config::class ),
			$localisationCache,
			$matcher
		);
		$result = $hooks->onMessageCache__get( $key );
		$this->assertTrue( $result );
	}

	public function testShouldReturnFalseUnknownButMatchedKey() {
		$key = 'matched_key';
		$localisationCache = $this->createMock( LocalisationCache::class );
		$matcher = new MessageCachePerformanceMatcher( [ $key ] );

		$localisationCache->expects( $this->once() )
			->method( 'getSubitemList' )
			->willReturn( [ 'known_key' ] );

		$hooks = new Hooks(
			$this->createMock( Config::class ),
			$localisationCache,
			$matcher
		);
		$result = $hooks->onMessageCache__get( $key );
		$this->assertFalse( $result );
	}

	/**
	 * @dataProvider matcherDataProvider
	 */
	public function testShouldReturnFalseUnknownButMatchedOnPrefixKey(
		array $matcher,
		array $keysAndResult
	) {
		$localisationCache = $this->createMock( LocalisationCache::class );
		$matcher = new MessageCachePerformanceMatcher( $matcher );

		$localisationCache->expects( $this->once() )
			->method( 'getSubitemList' )
			->willReturn( [ 'some_key' ] );

		$hooks = new Hooks(
			$this->createMock( Config::class ),
			$localisationCache,
			$matcher
		);

		foreach ( $keysAndResult as $key => $expectedResult ) {
			$this->assertEquals( $expectedResult, $hooks->onMessageCache__get( $key ) );
		}
	}

	public static function matcherDataProvider() {
		return [
			'single key' => [ [ 'matched_key' ], [ 'matched_key' => false ] ],
			'multiple keys' => [
				[ 'matched_key', 'another_matched_key' ],
				[
					'matched_key' => false,
					'non_matched_key' => true,
					'another_matched_key' => false,
				],
			],
			'single prefix key' => [ [ 'prefix_' ], [ 'prefix_matched_key' => false ] ],
			'multiple prefix keys' => [
				[ 'prefix_' ],
				[
					'prefix_matched_key' => false,
					'not_prefix_matched_key' => true,
					'prefix_another_one' => false
				]
			],
			'multiple prefixes with single match' => [
				[ 'prefix_', 'another_prefix' ],
				[
					'prefix_matched_key' => false,
					'not_prefix_matched_key' => true,
					'another_one' => true
				]
			],
			'multiple prefixes with matches' => [
				[ 'prefix_', 'another_prefix' ],
				[
					'prefix_matched_key' => false,
					'not_prefix_matched_key' => true,
					'prefix_another_key' => false,
					'another_prefix_key' => false
				]
			],
			'multiple prefixes with center matches' => [
				[ 'prefix_', 'another_prefix' ],
				[
					'not_prefix_matched_key' => true,
					'not_another_prefix_key' => true,
					'random_prefix_abc' => true,
					'another_prefix_key' => false
				]
			],
			'multiple prefixes with special characters' => [
				[ 'prefix.', '.*prefix', '|prefix...' ],
				[
					'|prefix..._matched_key' => false,
					'.*prefix_matched_key' => false,
					'prefix._matched_key' => false,
					'not_prefix_matched_key' => true,
				]
			],
		];
	}
}
