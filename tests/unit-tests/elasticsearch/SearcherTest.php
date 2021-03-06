<?php
namespace elasticsearch;

class SearcherTest extends BaseTestCase
{
	public function testFacetNoFacets()
	{
		$facets = array();

		$searcher = new Searcher();

		$musts = array();
		$filters = array();

		$searcher->_filterBySelectedFacets('facet', $facets, 'term', $musts, $filters);

		$this->assertCount(0, $musts);
		$this->assertCount(0, $filters);
	}

	public function testFacetSingle()
	{
		$facets = array(
			'facet' => 'value'
		);

		$searcher = new Searcher();

		$musts = array();
		$filters = array();

		$searcher->_filterBySelectedFacets('facet', $facets, 'term', $musts, $filters);

		$this->assertCount(1, $musts);
		$this->assertCount(0, $filters);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'value'
				)
			)
		), $musts);
	}

	public function testFacetSingleTransalte()
	{
		$facets = array(
			'facet' => 'value'
		);

		$searcher = new Searcher();

		$musts = array();
		$filters = array();
		$translate = array(
			'value' => 'foobar'
		);

		$searcher->_filterBySelectedFacets('facet', $facets, 'term', $musts, $filters, $translate);

		$this->assertCount(1, $musts);
		$this->assertCount(0, $filters);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'foobar'
				)
			)
		), $musts);
	}

	public function testFacetMultipleNumKey()
	{
		$facets = array(
			'facet' => array('value1', 'value2')
		);

		$searcher = new Searcher();

		$musts = array();
		$filters = array();

		$searcher->_filterBySelectedFacets('facet', $facets, 'term', $musts, $filters);

		$this->assertCount(2, $musts);
		$this->assertCount(0, $filters);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'value1'
				)
			),
			array(
				'term' => array(
					'facet' => 'value2'
				)
			)
		), $musts);
	}

	public function testFacetMultipleStringKeyArray()
	{
		$facets = array(
			'facet' => array(
				'and' => array('value1', 'value2'),
				'or' => array('value3', 'value4')
			)
		);

		$searcher = new Searcher();

		$musts = array();
		$filters = array();

		$searcher->_filterBySelectedFacets('facet', $facets, 'term', $musts, $filters);

		$this->assertCount(2, $musts);
		$this->assertCount(2, $filters);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'value1'
				)
			),
			array(
				'term' => array(
					'facet' => 'value2'
				)
			)
		), $musts);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'value3'
				)
			),
			array(
				'term' => array(
					'facet' => 'value4'
				)
			)
		), $filters);
	}

	public function testFacetMultipleStringKeySingle()
	{
		$facets = array(
			'facet' => array(
				'and' => 'value1',
				'or' => 'value2'
			)
		);

		$searcher = new Searcher();

		$musts = array();
		$filters = array();

		$searcher->_filterBySelectedFacets('facet', $facets, 'term', $musts, $filters);

		$this->assertCount(1, $musts);
		$this->assertCount(1, $filters);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'value1'
				)
			)
		), $musts);

		$this->assertEquals(array(
			array(
				'term' => array(
					'facet' => 'value2'
				)
			)
		), $filters);
	}

	public function testBuildQuery()
	{
		update_option('fields', array());

		$searcher = new Searcher();

		$query = $searcher->_buildQuery('string');

		$this->assertEquals(array(
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryFieldWithScore()
	{
		update_option('fields', array('field1' => 1));
		update_option('score_field_field1', 1);

		$searcher = new Searcher();

		$facets = array();

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'query_string' => array(
								'fields' => array('field1.english^1'),
								'query' => 'string'
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryTaxonomiesWithScoreFuzzy()
	{
		update_option('taxonomies', array('tax1' => 1));
		update_option('score_tax_tax1', 1);
		update_option('fields', array());

		$searcher = new Searcher();

		$facets = array();

		$query = $searcher->_buildQuery('string~', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'query_string' => array(
								'fields' => array('tax1_name^1'),
								'query' => 'string~'
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryTaxonomiesWithScore()
	{
		update_option('taxonomies', array('tax1' => 1));
		update_option('score_tax_tax1', 1);
		update_option('fields', array());

		$searcher = new Searcher();

		$facets = array();

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'query_string' => array(
								'fields' => array('tax1_name^1'),
								'query' => 'string'
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryTaxonomiesNotScored()
	{
		update_option('taxonomies', array('tax1' => 1));
		update_option('fields', array());

		$searcher = new Searcher();

		$facets = array();

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						),
					)
				)
			)
		), $query);
	}

	public function testBuildQueryTaxonomiesWithFaceting()
	{
		update_option('taxonomies', array('tax1' => 1));
		update_option('fields', array());

		$searcher = new Searcher();

		$facets = array(
			'tax1' => 'test'
		);

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'tax1' => 'test'
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryTaxonomiesWithFacetingShoulds()
	{
		update_option('taxonomies', array('tax1' => 1));
		update_option('fields', array());

		$searcher = new Searcher();

		$facets = array(
			'tax1' => array(
				'or' => array(
					'value1', 'value2'
				)
			)
		);

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'filter' => array(
				'bool' => array(
					'should' => array(
						array(
							'bool' => array(
								'should' => array(
									array(
										'term' => array(
											'tax1' => 'value1'
										)
									),
									array(
										'term' => array(
											'tax1' => 'value2'
										)
									)
								)
							)
						)
					),
					'minimum_should_match' => 1,
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryMultipleTaxonomiesWithFacetingShoulds()
	{
		update_option('taxonomies', array('tax1' => 1, 'tax2' => 1));
		update_option('fields', array());

		$searcher = new Searcher();

		$facets = array(
			'tax1' => array(
				'or' => array(
					'value1', 'value2'
				)
			),
			'tax2' => 'value3'
		);

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'tax2' => 'value3'
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'should' => array(
						array(
							'bool' => array(
								'should' => array(
									array(
										'term' => array(
											'tax1' => 'value1'
										)
									),
									array(
										'term' => array(
											'tax1' => 'value2'
										)
									)
								),
							),
						),
					),
					'minimum_should_match' => 1,
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						),
					),
				),
				'tax2' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							),
							'minimum_should_match' => 1,
							'should' => array(
								array(
									'bool' => array(
										'should' => array(
											array(
												'term' => array(
													'tax1' => 'value1'
												)
											),
											array(
												'term' => array(
													'tax1' => 'value2'
												)
											)
										),
									),
								),
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax2',
								'size' => 100
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryNumericNoRange()
	{
		update_option('numeric', array('field1' => 1));
		update_option('fields', array('field1' => 1, 'field2' => 1));

		$searcher = new Searcher();

		$facets = array();

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			)
		), $query);
	}

	public function testBuildQueryNumericWithRange()
	{
		update_option('numeric', array('field1' => 1));
		update_option('fields', array('field1' => 1, 'field2' => 1));
		update_option('field1_range', '-10,10-20,20-');

		$searcher = new Searcher();

		$facets = array();

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'field1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'range' => array(
							'range' => array(
								'field' => 'field1',
								'ranges' => array(
									array(
										'to' => '10'
									),
									array(
										'from' => '10',
										'to' => '20'
									),
									array(
										'from' => '20'
									),
								)
							)
						),
					),
				)
			)
		), $query);
	}

	public function testBuildQueryNumericWithRangeFacted()
	{
		update_option('numeric', array('field1' => 1));
		update_option('fields', array('field1' => 1, 'field2' => 1));
		update_option('field1_range', '-10,10-20,20-');

		$searcher = new Searcher();

		$shoulds = array();
		$filters = array();
		$musts = array();
		$facets = array(
			'field1' => '10-20'
		);

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'range' => array(
								'field1' => array(
									'gte' => 10,
									'lt' => 20
								)
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'field1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'range' => array(
							'range' => array(
								'field' => 'field1',
								'ranges' => array(
									array(
										'to' => '10'
									),
									array(
										'from' => '10',
										'to' => '20'
									),
									array(
										'from' => '20'
									),
								)
							)
						),
					)
				)
			)
		), $query);
	}

	public function testBuildQueryNumericWithRangeFactedWithTax()
	{
		update_option('numeric', array('field1' => 1));
		update_option('fields', array('field1' => 1, 'field2' => 1));
		update_option('field1_range', '-10,10-20,20-');
		update_option('taxonomies', array('tax1' => 1));

		$searcher = new Searcher();

		$shoulds = array();
		$filters = array();
		$musts = array();
		$facets = array(
			'field1' => '10-20',
			'tax1' => array(
				'or' => array(
					'value1', 'value2'
				)
			)
		);

		$query = $searcher->_buildQuery('string', $facets);

		$this->assertEquals(array(
			'query' => array(
				'bool' => array(
					'must' => array(
						array(
							'range' => array(
								'field1' => array(
									'gte' => 10,
									'lt' => 20
								)
							)
						)
					)
				)
			),
			'filter' => array(
				'bool' => array(
					'should' => array(
						array(
							'bool' => array(
								'should' => array(
									array(
										'term' => array(
											'tax1' => 'value1'
										)
									),
									array(
										'term' => array(
											'tax1' => 'value2'
										)
									)
								),
							),
						),
					),
					'minimum_should_match' => 1,
					'must' => array(
						array(
							'term' => array(
								'blog_id' => 1
							)
						)
					)
				)
			),
			'aggs' => array(
				'field1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'range' => array(
							'range' => array(
								'field' => 'field1',
								'ranges' => array(
									array(
										'to' => '10'
									),
									array(
										'from' => '10',
										'to' => '20'
									),
									array(
										'from' => '20'
									),
								)
							)
						)
					)
				),
				'tax1' => array(
					'filter' => array(
						'bool' => array(
							'must' => array(
								array(
									'term' => array(
										'blog_id' => 1
									)
								)
							)
						)
					),
					'aggs' => array(
						'facet' => array(
							'terms' => array(
								'field' => 'tax1',
								'size' => 100
							)
						)
					)
				)
			)
		), $query);
	}

	public function testParseResponse()
	{
		$searcher = new Searcher();

		$data = array(
			'hits' => array(
				'total' => 100,
				'hits' => array(
					array(
						'_score' => 5,
						'_id' => 1
					),
					array(
						'_score' => 2,
						'_id' => 10
					),
					array(
						'_score' => 3,
						'_id' => 100
					)
				)
			),
			'aggregations' => array(
				'name1' => array(
					'facet' => array(
						'buckets' => array(
							array(
								'key' => 'term1',
								'doc_count' => 10
							),
							array(
								'key' => 'term2',
								'doc_count' => 15
							)
						)
					)
				),
				'range1' => array(
					'range' => array(
						'buckets' => array(
							array(
								'from' => 0,
								'to' => 10,
								'doc_count' => 15
							),
							array(
								'from' => 10,
								'to' => 20,
								'doc_count' => 20
							)
						)
					)
				)
			)
		);

		$response = new \Elastica\Response(json_encode($data));
		$results = new \Elastica\ResultSet($response, new \Elastica\Query());

		$output = $searcher->_parseResults($results);

		$this->assertEquals(array(
			'total' => 100,
			'facets' => array(
				'name1' => array(
					'term1' => 10,
					'term2' => 15
				),
				'range1' => array(
					'0-10' => 15,
					'10-20' => 20
				)
			),
			'ids' => array(1, 10, 100)
		), $output);
	}
}

?>