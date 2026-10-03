import hashlib,hmac,json,sqlite3,sys,unittest,uuid
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from examples.webhook_receiver import verify_request,apply_once
class ReceiverTest(unittest.TestCase):
 def setUp(self):
  self.key='a'*64;self.now=1780000000;self.id=str(uuid.uuid4());self.event={'schema_version':1,'id':self.id,'type':'invoice.paid','occurred_at':'fixture','data':{'resource':'invoice','id':1}};self.body=json.dumps(self.event).encode()
 def sig(self,body=None,t=None):
  t=self.now if t is None else t;return 't='+str(t)+',v1='+hmac.new(self.key.encode(),str(t).encode()+b'.'+(self.body if body is None else body),hashlib.sha256).hexdigest()
 def verify(self,body=None,sig=None,event_id=None,kind='invoice.paid'):
  return verify_request(self.body if body is None else body,self.sig() if sig is None else sig,self.id if event_id is None else event_id,kind,self.key,now=self.now)
 def test_valid_event(self):self.assertEqual(self.event,self.verify())
 def test_tampering(self):
  with self.assertRaises(ValueError):self.verify(self.body+b' ')
 def test_expired_or_future_signature(self):
  for t in [self.now-301,self.now+301]:
   with self.assertRaises(ValueError):self.verify(sig=self.sig(t=t))
 def test_header_mismatch(self):
  with self.assertRaises(ValueError):self.verify(event_id=str(uuid.uuid4()))
  with self.assertRaises(ValueError):self.verify(kind='order.created')
 def test_repeated_or_malformed_headers(self):
  for sig in [self.sig()+',v1='+'0'*64,'v1='+'0'*64,'t=abc,v1='+'0'*64]:
   with self.assertRaises(ValueError):self.verify(sig=sig)
 def test_invalid_schema(self):
  for event in [[],{'schema_version':2},dict(self.event,data={'resource':'invoice','id':True})]:
   body=json.dumps(event).encode()
   with self.assertRaises(ValueError):self.verify(body,self.sig(body))
 def test_oversized_body(self):
  body=b'x'*16385
  with self.assertRaises(ValueError):self.verify(body,self.sig(body))
 def test_replay_deduplicates_business_effect(self):
  db=sqlite3.connect(':memory:');db.execute('CREATE TABLE effects (id INTEGER)');handler=lambda db,e:db.execute('INSERT INTO effects VALUES (?)',(e['data']['id'],));event=self.verify()
  self.assertTrue(apply_once(db,event,handler));self.assertFalse(apply_once(db,event,handler));self.assertEqual(1,db.execute('SELECT count(*) FROM effects').fetchone()[0])
 def test_failed_handler_rolls_back_marker_and_allows_retry(self):
  db=sqlite3.connect(':memory:');db.execute('CREATE TABLE effects (id INTEGER)')
  def fail(db,e):db.execute('INSERT INTO effects VALUES (1)');raise RuntimeError('fixture')
  with self.assertRaises(RuntimeError):apply_once(db,self.verify(),fail)
  self.assertEqual(0,db.execute('SELECT count(*) FROM lagos_received').fetchone()[0]);self.assertEqual(0,db.execute('SELECT count(*) FROM effects').fetchone()[0]);self.assertTrue(apply_once(db,self.verify(),lambda db,e:None))
if __name__=='__main__':unittest.main(verbosity=2)
