import unittest
from auto_fill_section_properties import build_plan, BASE_PROPERTY_IDS

class FillPlanningTest(unittest.TestCase):
    def test_inheritance_manual_untouched_and_product_additions(self):
        sections = [
            dict(id=1,parent=0,depth=1,name='root'),
            dict(id=2,parent=1,depth=2,name='manual child'),
            dict(id=3,parent=2,depth=3,name='new grandchild'),
            dict(id=4,parent=1,depth=2,name='new child'),
        ]
        plans = build_plan(sections, {100:{},200:{},300:{}},
                           {1:1,2:4}, {1:[100],2:[200]},
                           {2:{300},3:{300},4:{300}}, {3:1,4:1}, 0, base_ids=())
        self.assertEqual([p['section_id'] for p in plans],[3,4])
        self.assertEqual(plans[0]['property_ids'],[200,300])
        self.assertEqual(plans[1]['property_ids'],[100,300])

    def test_unconfigured_parent_carries_generated_set(self):
        sections = [dict(id=1,parent=0,depth=1,name='root'),
                    dict(id=2,parent=1,depth=2,name='child')]
        plans = build_plan(sections,{100:{},200:{}},{},{},
                           {1:{100},2:{200}}, {1:1,2:1}, 0, base_ids=())
        self.assertEqual(plans[1]['property_ids'],[100,200])

    def test_empty_skipped(self):
        sections = [dict(id=1,parent=0,depth=1,name='root')]
        plans = build_plan(sections,{},{},{},{},{},0, base_ids=())
        self.assertTrue(plans[0]['empty'])

    def test_subtree(self):
        sections = [dict(id=1,parent=0,depth=1,name='root'),
                    dict(id=2,parent=1,depth=2,name='child'),
                    dict(id=3,parent=2,depth=3,name='grandchild')]
        plans = build_plan(sections,{100:{}},{1:1},{1:[100]}, {}, {},2, base_ids=())
        self.assertEqual([p['section_id'] for p in plans],[2,3])
        self.assertEqual(plans[-1]['property_ids'],[100])

    def test_base_first_and_unique_product_properties(self):
        props = {pid:{'code':'PROP1','name':'x'} for pid in BASE_PROPERTY_IDS}
        props[110] = {'code':'PROP24','name':'Height'}
        props[109] = {'code':'PROP23','name':'Length'}
        sections = [dict(id=1828,parent=0,depth=1,name='standalone')]
        plans = build_plan(sections, props, {}, {}, {1828:{338,89,110,109}}, {1828:1}, 0)
        self.assertEqual(plans[0]['property_ids'], list(BASE_PROPERTY_IDS)+[109,110])
        self.assertEqual(plans[0]['base'], 8)
        self.assertEqual(plans[0]['discovered'], 2)

    def test_existing_category_stays_unchanged(self):
        props = {pid:{} for pid in BASE_PROPERTY_IDS}
        sections = [dict(id=1,parent=0,depth=1,name='configured')]
        plans = build_plan(sections, props, {1:1}, {1:[89]}, {1:{338}}, {1:1}, 0)
        self.assertEqual(plans, [])

    def test_missing_common_field_blocks_incomplete_plan(self):
        sections = [dict(id=1,parent=0,depth=1,name='root')]
        with self.assertRaises(RuntimeError):
            build_plan(sections, {338:{}}, {}, {}, {}, {}, 0)

if __name__ == '__main__':
    unittest.main()
