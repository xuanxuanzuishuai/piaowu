import time
import json
import hashlib
import requests


appid = 'si_xyz1113_test'
secret = '97e5b3ddbcb4b1bd2faf9349c575d771'


def make_sign(timestamp, body):
    m = hashlib.md5()
    s = appid + timestamp + json.dumps(body) + secret
    m.update(s.encode("utf8"))
    return m.hexdigest()


def send_post():

    url = "http://39.97.47.26/crm/fieldMapping"

    body = {
        '创建时间':'creatTime',
        '姓名': 'name',
        '微信昵称':'nickname',
        '微信备注': 'wechatMark',
        '电话号码': 'mobile',
        '上次跟进': 'lastTimeFollow',
        '销售阶段': 'salesStage',
        '意向': 'intention',
        '来源': 'source',
        '有效对话': 'effectiveCommunication',
        '微信号':'wechatNumber',
        '性别':'gender',
        '生日': 'birthday',
        '备注': 'comments'
    }

    timestamp = str(int(time.time() * 1000))
    print(timestamp)

    headers = {
        'appid': appid,
        'timestamp': timestamp,
        'sign': make_sign(timestamp, body),
        'Content-Type': 'application/json',
    }

    response = requests.request("POST", url, headers=headers, data=json.dumps(body))
    print(response.text)



send_post()
