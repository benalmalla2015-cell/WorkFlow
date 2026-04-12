import React, { useState, useEffect } from 'react';
import { Form, Input, InputNumber, Button, Card, Row, Col, message, Divider, Typography, Spin } from 'antd';
import { SaveOutlined, SettingOutlined, BankOutlined, PercentageOutlined } from '@ant-design/icons';
import AppLayout from '../../components/AppLayout';
import axios from 'axios';

const { Title, Text } = Typography;
const { TextArea } = Input;

export default function AdminSettings() {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    axios.get('/api/admin/settings')
      .then(res => {
        form.setFieldsValue({
          company_name: res.data.company_name || 'DAYANCO TRADING CO., LIMITED',
          company_address: res.data.company_address || 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU 511455, P.R. CHINA',
          company_phone: res.data.company_phone || '+86 188188 45411',
          company_email: res.data.company_email || 'team@dayancofficial.com',
          company_attn: res.data.company_attn || 'Mr. Abdulmalek',
          default_profit_margin: parseFloat(res.data.default_profit_margin || 20),
          beneficiary_name: res.data.beneficiary_name || 'DAYANCO TRADING CO., LIMITED',
          beneficiary_bank: res.data.beneficiary_bank || 'ZHEJIANG CHOUZHOU COMMERCIAL BANK',
          account_number: res.data.account_number || 'NRA1564714201050006871',
          swift_code: res.data.swift_code || 'CZCBCNLX',
          bank_address: res.data.bank_address || 'YIWULEYUAN EAST, JIANGBEI RD, YIWU, ZHEJIANG CHINA',
          beneficiary_address: res.data.beneficiary_address || '9F, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000 P.R. CHINA',
        });
      })
      .catch(() => message.error('Failed to load settings'))
      .finally(() => setLoading(false));
  }, []);

  const onFinish = async (values) => {
    setSaving(true);
    try {
      await axios.put('/api/admin/settings', values);
      message.success('Settings saved successfully!');
    } catch (err) {
      message.error(err.response?.data?.message || 'Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <AppLayout><Spin size="large" style={{ display: 'block', margin: '80px auto' }} /></AppLayout>;

  return (
    <AppLayout>
      <div style={{ padding: '24px', maxWidth: 860, margin: '0 auto' }}>
        <Title level={3}><SettingOutlined /> System Settings</Title>

        <Form form={form} layout="vertical" onFinish={onFinish}>

          {/* Profit Margin */}
          <Card
            title={<><PercentageOutlined /> Default Profit Margin</>}
            style={{ marginBottom: 20, borderLeft: '4px solid #667eea' }}
          >
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="default_profit_margin"
                  label="Default Profit Margin (%)"
                  help="This is applied automatically when factory submits pricing. Admin can override per order."
                  rules={[{ required: true }]}
                >
                  <InputNumber
                    min={0} max={500} step={0.5}
                    style={{ width: '100%' }}
                    formatter={v => `${v}%`}
                    parser={v => v.replace('%', '')}
                    size="large"
                  />
                </Form.Item>
              </Col>
              <Col span={12} style={{ paddingTop: 40 }}>
                <Text type="secondary">
                  Example: Factory cost = $10, Margin = 30% → Sale price = $13
                </Text>
              </Col>
            </Row>
          </Card>

          {/* Company Info */}
          <Card
            title={<><SettingOutlined /> Company Information (appears on documents)</>}
            style={{ marginBottom: 20 }}
          >
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="company_name" label="Company Name" rules={[{ required: true }]}>
                  <Input placeholder="DAYANCO TRADING CO., LIMITED" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="company_attn" label="Contact Person (ATTN)" rules={[{ required: true }]}>
                  <Input placeholder="Mr. Abdulmalek" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="company_phone" label="Phone" rules={[{ required: true }]}>
                  <Input placeholder="+86 188188 45411" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="company_email" label="Email" rules={[{ required: true }]}>
                  <Input placeholder="team@dayancofficial.com" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="company_address" label="Company Address" rules={[{ required: true }]}>
                  <TextArea rows={2} placeholder="Full company address" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Bank Details */}
          <Card
            title={<><BankOutlined /> Bank / Payment Details (appears on invoices)</>}
            style={{ marginBottom: 20 }}
          >
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="beneficiary_name" label="Beneficiary Name" rules={[{ required: true }]}>
                  <Input placeholder="DAYANCO TRADING CO., LIMITED" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="beneficiary_bank" label="Beneficiary Bank" rules={[{ required: true }]}>
                  <Input placeholder="ZHEJIANG CHOUZHOU COMMERCIAL BANK" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="account_number" label="Account Number" rules={[{ required: true }]}>
                  <Input placeholder="NRA1564714201050006871" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="swift_code" label="SWIFT Code" rules={[{ required: true }]}>
                  <Input placeholder="CZCBCNLX" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="beneficiary_address" label="Beneficiary Address">
                  <TextArea rows={2} placeholder="Beneficiary full address" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="bank_address" label="Bank Address">
                  <TextArea rows={2} placeholder="Bank full address" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          <Button
            type="primary" htmlType="submit"
            icon={<SaveOutlined />}
            loading={saving}
            size="large"
            block
            style={{ background: '#667eea', borderColor: '#667eea' }}
          >
            Save All Settings
          </Button>
        </Form>
      </div>
    </AppLayout>
  );
}
