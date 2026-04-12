import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Card, Button, Tag, Space, message, Modal, Row, Col, Select, DatePicker } from 'antd';
import { 
  PlusOutlined, 
  EyeOutlined, 
  EditOutlined, 
  FileExcelOutlined, 
  FilePdfOutlined,
  CheckOutlined,
  DollarOutlined,
  SearchOutlined,
  SendOutlined
} from '@ant-design/icons';
import { useAuth } from '../../contexts/AuthContext';
import AppLayout from '../../components/AppLayout';
import axios from 'axios';
import moment from 'moment';

const { Option } = Select;
const { RangePicker } = DatePicker;

const SalesOrders = () => {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [filters, setFilters] = useState({});
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [modalVisible, setModalVisible] = useState(false);
  const navigate = useNavigate();
  const { user } = useAuth();

  useEffect(() => {
    fetchOrders();
  }, [pagination.current, pagination.pageSize, filters]);

  const fetchOrders = async () => {
    try {
      setLoading(true);
      const params = {
        page: pagination.current,
        per_page: pagination.pageSize,
        ...filters,
      };

      const response = await axios.get('/api/orders', { params });
      setOrders(response.data.data);
      setPagination(prev => ({
        ...prev,
        total: response.data.total,
      }));
    } catch (error) {
      message.error('Failed to fetch orders');
    } finally {
      setLoading(false);
    }
  };

  const handleTableChange = (paginationConfig) => {
    setPagination(prev => ({
      ...prev,
      current: paginationConfig.current,
      pageSize: paginationConfig.pageSize,
    }));
  };

  const generateQuotation = async (orderId) => {
    try {
      setLoading(true);
      const response = await axios.post(`/api/orders/${orderId}/quotation`);
      message.success('Quotation generated successfully');
      
      // Download the file
      window.open(response.data.download_url, '_blank');
      fetchOrders(); // Refresh orders
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to generate quotation');
    } finally {
      setLoading(false);
    }
  };

  const generateInvoice = async (orderId) => {
    try {
      setLoading(true);
      const response = await axios.post(`/api/orders/${orderId}/invoice`);
      message.success('Invoice generated successfully');
      
      // Download the file
      window.open(response.data.download_url, '_blank');
      fetchOrders(); // Refresh orders
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to generate invoice');
    } finally {
      setLoading(false);
    }
  };

  const customerApproval = async (orderId) => {
    try {
      await axios.post(`/api/orders/${orderId}/customer-approval`);
      message.success('Customer approval recorded');
      fetchOrders();
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to record approval');
    }
  };

  const confirmPayment = async (orderId) => {
    try {
      await axios.post(`/api/orders/${orderId}/confirm-payment`);
      message.success('Payment confirmed');
      fetchOrders();
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to confirm payment');
    }
  };

  const submitToFactory = async (orderId) => {
    try {
      await axios.patch(`/api/orders/${orderId}/status`, { status: 'factory_pricing' });
      message.success('Order submitted to factory pricing');
      fetchOrders();
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to submit order to factory');
    }
  };

  const viewOrder = (order) => {
    setSelectedOrder(order);
    setModalVisible(true);
  };

  const getStatusColor = (status) => {
    const colors = {
      draft: 'default',
      factory_pricing: 'processing',
      manager_review: 'warning',
      approved: 'success',
      customer_approved: 'blue',
      payment_confirmed: 'green',
      completed: 'purple',
    };
    return colors[status] || 'default';
  };

  const getStatusText = (status) => {
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  };

  const columns = [
    {
      title: 'Order Number',
      dataIndex: 'order_number',
      key: 'order_number',
      render: (text) => <strong>{text}</strong>,
    },
    {
      title: 'Customer',
      dataIndex: ['customer', 'full_name'],
      key: 'customer',
    },
    {
      title: 'Product',
      dataIndex: 'product_name',
      key: 'product_name',
    },
    {
      title: 'Quantity',
      dataIndex: 'quantity',
      key: 'quantity',
    },
    {
      title: 'Final Price',
      dataIndex: 'final_price',
      key: 'final_price',
      render: (price) => price ? `$${Number(price).toFixed(2)}` : '-',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => (
        <Tag color={getStatusColor(status)}>
          {getStatusText(status)}
        </Tag>
      ),
    },
    {
      title: 'Created Date',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => moment(date).format('YYYY-MM-DD'),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_, record) => (
        <Space size="small">
          <Button 
            size="small" 
            icon={<EyeOutlined />} 
            onClick={() => viewOrder(record)}
          >
            View
          </Button>
          {record.status === 'draft' && (
            <Button 
              size="small" 
              icon={<EditOutlined />} 
              onClick={() => navigate(`/sales/orders/${record.id}/edit`)}
            >
              Edit
            </Button>
          )}
          {record.status === 'draft' && (
            <Button
              size="small"
              icon={<SendOutlined />}
              onClick={() => submitToFactory(record.id)}
            >
              Send to Factory
            </Button>
          )}
          {record.status === 'approved' && (
            <Button 
              size="small" 
              icon={<FileExcelOutlined />} 
              onClick={() => generateQuotation(record.id)}
            >
              Quotation
            </Button>
          )}
          {['payment_confirmed', 'completed'].includes(record.status) && (
            <Button 
              size="small" 
              icon={<FilePdfOutlined />} 
              onClick={() => generateInvoice(record.id)}
            >
              Invoice
            </Button>
          )}
          {record.status === 'approved' && !record.customer_approval && (
            <Button 
              size="small" 
              type="primary" 
              icon={<CheckOutlined />} 
              onClick={() => customerApproval(record.id)}
            >
              Customer Approval
            </Button>
          )}
          {record.status === 'customer_approved' && !record.payment_confirmed && (
            <Button 
              size="small" 
              type="primary" 
              icon={<DollarOutlined />} 
              onClick={() => confirmPayment(record.id)}
            >
              Confirm Payment
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <AppLayout>
    <div style={{ padding: '0' }}>
      <Card 
        title="My Orders" 
        extra={
          <Button 
            type="primary" 
            icon={<PlusOutlined />}
            onClick={() => navigate('/sales/orders/new')}
          >
            Create New Order
          </Button>
        }
      >
        {/* Filters */}
        <div style={{ marginBottom: '16px', display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
          <Select
            placeholder="Filter by Status"
            style={{ width: 200 }}
            allowClear
            onChange={(value) => setFilters({ ...filters, status: value })}
          >
            <Option value="draft">Draft</Option>
            <Option value="factory_pricing">Factory Pricing</Option>
            <Option value="manager_review">Manager Review</Option>
            <Option value="approved">Approved</Option>
            <Option value="customer_approved">Customer Approved</Option>
            <Option value="payment_confirmed">Payment Confirmed</Option>
            <Option value="completed">Completed</Option>
          </Select>
          
          <RangePicker
            placeholder={['Start Date', 'End Date']}
            onChange={(dates) => {
              if (dates) {
                setFilters({
                  ...filters,
                  date_from: dates[0].format('YYYY-MM-DD'),
                  date_to: dates[1].format('YYYY-MM-DD'),
                });
              } else {
                const { date_from, date_to, ...rest } = filters;
                setFilters(rest);
              }
            }}
          />
        </div>

        <Table
          columns={columns}
          dataSource={orders}
          rowKey="id"
          pagination={pagination}
          onChange={handleTableChange}
          loading={loading}
        />
      </Card>

      {/* Order Details Modal */}
      <Modal
        title="Order Details"
        visible={modalVisible}
        onCancel={() => setModalVisible(false)}
        footer={null}
        width={800}
      >
        {selectedOrder && (
          <div>
            <Row gutter={16}>
              <Col span={12}>
                <p><strong>Order Number:</strong> {selectedOrder.order_number}</p>
                <p><strong>Customer:</strong> {selectedOrder.customer?.full_name}</p>
                <p><strong>Phone:</strong> {selectedOrder.customer?.phone}</p>
                <p><strong>Address:</strong> {selectedOrder.customer?.address}</p>
              </Col>
              <Col span={12}>
                <p><strong>Product:</strong> {selectedOrder.product_name}</p>
                <p><strong>Quantity:</strong> {selectedOrder.quantity}</p>
                <p><strong>Final Price:</strong> ${selectedOrder.final_price || 'N/A'}</p>
                <p><strong>Status:</strong> <Tag color={getStatusColor(selectedOrder.status)}>{getStatusText(selectedOrder.status)}</Tag></p>
              </Col>
            </Row>
            {selectedOrder.specifications && (
              <div style={{ marginTop: '16px' }}>
                <strong>Specifications:</strong>
                <p>{selectedOrder.specifications}</p>
              </div>
            )}
            {selectedOrder.customer_notes && (
              <div style={{ marginTop: '16px' }}>
                <strong>Customer Notes:</strong>
                <p>{selectedOrder.customer_notes}</p>
              </div>
            )}
            {selectedOrder.attachments && selectedOrder.attachments.length > 0 && (
              <div style={{ marginTop: '16px' }}>
                <strong>Attachments:</strong>
                <ul>
                  {selectedOrder.attachments.map((attachment) => (
                    <li key={attachment.id}>
                      <a href={attachment.full_url} target="_blank" rel="noopener noreferrer">
                        {attachment.original_name}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
    </AppLayout>
  );
};

export default SalesOrders;
